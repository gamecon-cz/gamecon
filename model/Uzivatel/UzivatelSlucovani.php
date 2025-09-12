<?php

namespace Gamecon\Uzivatel;

use Symfony\Component\Filesystem\Filesystem;
use Uzivatel;

class UzivatelSlucovani
{
    private string $databaseName;

    public function __construct(string $databaseName)
    {
        $this->databaseName = $databaseName;
    }

    /**
     * @param array<string, mixed> $zmeny páry sloupec => hodnota, které se mají upravit v novém uživateli
     */
    public function sluc(
        Uzivatel $staryUzivatel,
        Uzivatel $novyUzivatel,
        array    $zmeny,
    ): void {
        $idStarehoUzivatele = (int)$staryUzivatel->id();
        $idNovehoUzivatele  = (int)$novyUzivatel->id();

        if ($idStarehoUzivatele === $idNovehoUzivatele) {
            return;
        }

        dbBegin();
        try {
            // 1) Update nového uživatele se změnami
            dbUpdate('uzivatele_hodnoty', $zmeny, ['id_uzivatele' => $idNovehoUzivatele]);

            // 2) Převod zůstatku ze starého na nového uživatele
            dbQuery("UPDATE uzivatele_hodnoty SET zustatek = zustatek + (SELECT zustatek FROM uzivatele_hodnoty u2 WHERE u2.id_uzivatele = $idStarehoUzivatele) WHERE id_uzivatele = $idNovehoUzivatele");

            // 3) Převod všech odkazovaných dat na nového uživatele pomocí FK
            $odkazujiciTabulky = $this->najdiOdkazujiciTabulky();

            foreach ($odkazujiciTabulky as $tabulka) {
                $this->prevedDataVTabulce($tabulka['table_name'], $tabulka['column_name'], $idStarehoUzivatele, $idNovehoUzivatele);
            }

            // 4) Smazání starého uživatele - FK CASCADE automaticky smaže související data
            dbQuery("DELETE FROM `uzivatele_hodnoty` WHERE `id_uzivatele` = $idStarehoUzivatele");

            dbCommit();
        } catch (\Throwable $e) {
            dbRollback();
            throw $e;
        }

        // logování po úspěšném commitu
        $this->zaloguj("do ID $idNovehoUzivatele sloučeno a smazáno ID $idStarehoUzivatele");
        $this->zaloguj("  zůstatek z předchozích ročníků smazaného účtu:    " . $staryUzivatel->finance()->zustatekZPredchozichRocniku());
        $this->zaloguj("  zůstatek z předchozích ročníků nového účtu:       " . $novyUzivatel->finance()->zustatekZPredchozichRocniku());
        $this->zaloguj("  email smazaného účtu:                             " . $staryUzivatel->mail());
        $this->zaloguj("  email nového účtu:                                " . $novyUzivatel->mail());
        $novyUzivatel = Uzivatel::zId($novyUzivatel->id()); // přenačtení uživatele, aby se aktualizovaly finance
        $this->zaloguj("  aktuální nový zůstatek z předchozích ročníků:     " . $novyUzivatel->finance()->zustatekZPredchozichRocniku());
        $this->zaloguj("  aktuální nový email:                              " . $novyUzivatel->mail() . "\n");
    }

    /**
     * Najde všechny tabulky, které mají foreign key na uzivatele_hodnoty.id_uzivatele
     * @return array<array{table_name: string, column_name: string}>
     */
    private function najdiOdkazujiciTabulky(): array
    {
        $sql = "
            SELECT 
                key_column_usage.TABLE_NAME as table_name,
                key_column_usage.COLUMN_NAME as column_name
            FROM 
                information_schema.KEY_COLUMN_USAGE key_column_usage
            WHERE 
                key_column_usage.CONSTRAINT_SCHEMA = $1
                AND key_column_usage.REFERENCED_TABLE_NAME = 'uzivatele_hodnoty'
                AND key_column_usage.REFERENCED_COLUMN_NAME = 'id_uzivatele'
                AND key_column_usage.TABLE_NAME != 'uzivatele_hodnoty'
        ";

        $result = dbQuery($sql, [1 => $this->databaseName]);
        $tabulky = [];

        while ($row = $result->fetch_assoc()) {
            $tabulky[] = $row;
        }

        return $tabulky;
    }

    /**
     * Převede data v tabulce ze starého uživatele na nového s ošetřením unique constraints
     */
    private function prevedDataVTabulce(string $tabulka, string $sloupec, int $idStarehoUzivatele, int $idNovehoUzivatele): void
    {
        // Nejdřív zjistíme, zda má tabulka unique constraints obsahující id_uzivatele
        $uniqueConstraints = $this->najdiUniqueConstraints($tabulka, $sloupec);

        if (!empty($uniqueConstraints)) {
            // Pokud jsou unique constraints, musíme nejdřív smazat konfliktní záznamy starého uživatele
            $this->smazKonfliktniZaznamy($tabulka, $sloupec, $idStarehoUzivatele, $idNovehoUzivatele, $uniqueConstraints);
        }

        // Teď můžeme bezpečně převést zbývající data
        dbQuery("UPDATE `$tabulka` SET `$sloupec` = $idNovehoUzivatele WHERE `$sloupec` = $idStarehoUzivatele");
    }

    /**
     * Najde unique constraints obsahující daný sloupec
     * @return array<array{constraint_name: string, columns: array<string>}>
     */
    private function najdiUniqueConstraints(string $tabulka, string $sloupec): array
    {
        $sql = "
            SELECT 
                table_constraints.CONSTRAINT_NAME as constraint_name,
                GROUP_CONCAT(key_column_usage.COLUMN_NAME ORDER BY key_column_usage.ORDINAL_POSITION) as columns
            FROM 
                information_schema.TABLE_CONSTRAINTS table_constraints
                JOIN information_schema.KEY_COLUMN_USAGE key_column_usage
                    ON table_constraints.TABLE_SCHEMA = key_column_usage.TABLE_SCHEMA 
                    AND table_constraints.TABLE_NAME = key_column_usage.TABLE_NAME 
                    AND table_constraints.CONSTRAINT_NAME = key_column_usage.CONSTRAINT_NAME
            WHERE 
                table_constraints.TABLE_SCHEMA = $1
                AND table_constraints.TABLE_NAME = $2
                AND table_constraints.CONSTRAINT_TYPE = 'UNIQUE'
                AND table_constraints.CONSTRAINT_NAME IN (
                    SELECT DISTINCT key_column_usage_for_unique.CONSTRAINT_NAME
                    FROM information_schema.KEY_COLUMN_USAGE key_column_usage_for_unique
                    WHERE key_column_usage_for_unique.TABLE_SCHEMA = $3
                        AND key_column_usage_for_unique.TABLE_NAME = $4
                        AND key_column_usage_for_unique.COLUMN_NAME = $5
                )
            GROUP BY table_constraints.CONSTRAINT_NAME
        ";

        $result = dbQuery($sql, [1 => $this->databaseName, 2 => $tabulka, 3 => $this->databaseName, 4 => $tabulka, 5 => $sloupec]);
        $constraints = [];

        while ($row = $result->fetch_assoc()) {
            $constraints[] = [
                'constraint_name' => $row['constraint_name'],
                'columns' => explode(',', $row['columns'])
            ];
        }

        return $constraints;
    }

    /**
     * Smaže konfliktní záznamy starého uživatele, které by porušily unique constraints
     */
    private function smazKonfliktniZaznamy(
        string $tabulka,
        string $sloupec,
        int $idStarehoUzivatele,
        int $idNovehoUzivatele,
        array $uniqueConstraints
    ): void {
        foreach ($uniqueConstraints as $constraint) {
            // Sestavíme WHERE podmínku pro nalezení konfliktů
            $whereColumns = [];
            foreach ($constraint['columns'] as $col) {
                if ($col !== $sloupec) {
                    $whereColumns[] = "old_user.`$col` = new_user.`$col`";
                }
            }

            if (empty($whereColumns)) {
                // Pokud je unique constraint pouze na id_uzivatele, není co řešit
                continue;
            }

            $whereCondition = implode(' AND ', $whereColumns);

            // Smažeme záznamy starého uživatele, které by způsobily konflikt
            $sql = "
                DELETE old_user FROM `$tabulka` old_user
                WHERE old_user.`$sloupec` = $idStarehoUzivatele
                    AND EXISTS (
                        SELECT 1 FROM (SELECT * FROM `$tabulka`) new_user
                        WHERE new_user.`$sloupec` = $idNovehoUzivatele
                            AND $whereCondition
                    )
            ";

            dbQuery($sql);
        }
    }

    /**
     * Zapíše zprávu do logu slučování uživatelů.
     */
    private function zaloguj(string $zprava): void
    {
        (new Filesystem())->mkdir(LOGY);
        $soubor = LOGY . '/slucovani.log';
        $cas    = date('Y-m-d H:i:s');
        file_put_contents($soubor, "$cas $zprava\n", FILE_APPEND);
    }
}
