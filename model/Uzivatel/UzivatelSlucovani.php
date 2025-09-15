<?php

namespace Gamecon\Uzivatel;

use Symfony\Component\Filesystem\Filesystem;
use Uzivatel;
use Gamecon\Uzivatel\SqlStruktura\UzivateleHodnotySqlStruktura as Sql;

class UzivatelSlucovani
{
    /**
     * @param array<string, mixed> $zmeny páry sloupec => hodnota, které se mají upravit v novém uživateli
     */
    public function sluc(
        Uzivatel $staryUzivatel,
        Uzivatel $novyUzivatel,
        array    $zmeny,
    ): void {
        if (array_key_exists(Sql::ZUSTATEK, $zmeny)) {
            throw new \InvalidArgumentException('Zůstatek nelze měnit při slučování uživatelů, je automaticky převeden ze starého uživatele na nového.');
        }

        $idStarehoUzivatele = (int)$staryUzivatel->id();
        $idNovehoUzivatele  = (int)$novyUzivatel->id();

        if ($idStarehoUzivatele === $idNovehoUzivatele) {
            return;
        }

        dbBegin();
        try {
            // Anonymizace unikátních polí starého uživatele pro zabránění konfliktům duplicitních klíčů
            $unikatniPole = $this->najdiUnikatniPole();
            $unikatniData = [];
            foreach ($unikatniPole as $pole) {
                $unikatniData[$pole] = uniqid('slucovani_');
            }
            if ($unikatniData !== []) {
                dbUpdate(Sql::UZIVATELE_HODNOTY_TABULKA, $unikatniData, ['id_uzivatele' => $idStarehoUzivatele]);
            }

            // Update nového uživatele se změnami
            dbUpdate(Sql::UZIVATELE_HODNOTY_TABULKA, $zmeny, ['id_uzivatele' => $idNovehoUzivatele]);

            // Převod zůstatku ze starého na nového uživatele
            dbQuery("UPDATE uzivatele_hodnoty SET zustatek = zustatek + (SELECT zustatek FROM uzivatele_hodnoty u2 WHERE u2.id_uzivatele = $idStarehoUzivatele) WHERE id_uzivatele = $idNovehoUzivatele");

            // Převod všech odkazovaných dat na nového uživatele pomocí FK
            $odkazujiciTabulky = $this->najdiOdkazujiciTabulky();

            foreach ($odkazujiciTabulky as $tabulka) {
                $this->prevedDataVTabulce($tabulka['table_name'], $tabulka['column_name'], $idStarehoUzivatele, $idNovehoUzivatele);
            }

            // Smazání starého uživatele - FK CASCADE automaticky smaže související data
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
     * Najde všechny sloupce, které mají UNIQUE klíč v tabulce uzivatele_hodnoty
     * @return array<string>
     */
    private function najdiUnikatniPole(): array
    {
        $result = dbQuery("
            SELECT COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE CONSTRAINT_SCHEMA = (SELECT DATABASE())
                AND TABLE_NAME = $1
                AND CONSTRAINT_NAME != 'PRIMARY'
                AND CONSTRAINT_NAME IN (
                    SELECT CONSTRAINT_NAME
                    FROM information_schema.TABLE_CONSTRAINTS
                    WHERE CONSTRAINT_SCHEMA = (SELECT DATABASE())
                        AND TABLE_NAME = $1
                        AND CONSTRAINT_TYPE = 'UNIQUE'
                )
        ", [1 => Sql::UZIVATELE_HODNOTY_TABULKA]);

        $unikatniPole = [];

        while ($row = $result->fetch_assoc()) {
            $unikatniPole[] = $row['COLUMN_NAME'];
        }

        return $unikatniPole;
    }

    /**
     * Najde všechny tabulky, které mají foreign key na uzivatele_hodnoty.id_uzivatele
     * @return array<array{table_name: string, column_name: string}>
     */
    private function najdiOdkazujiciTabulky(): array
    {
        $result = dbQuery("
            SELECT 
                key_column_usage.TABLE_NAME AS table_name,
                key_column_usage.COLUMN_NAME AS column_name
            FROM 
                information_schema.KEY_COLUMN_USAGE key_column_usage
            WHERE 
                key_column_usage.CONSTRAINT_SCHEMA = (SELECT DATABASE())
                AND key_column_usage.REFERENCED_TABLE_NAME = $1
                AND key_column_usage.REFERENCED_COLUMN_NAME = $2
                AND key_column_usage.TABLE_NAME != $1
        ", [1 => Sql::UZIVATELE_HODNOTY_TABULKA, 2 => Sql::ID_UZIVATELE]);

        $tabulky = [];

        while ($row = $result->fetch_assoc()) {
            $tabulky[] = $row;
        }

        return $tabulky;
    }

    /**
     * Převede data v tabulce ze starého uživatele na nového s ošetřením unique constraints
     */
    private function prevedDataVTabulce(
        string $tabulka,
        string $sloupec,
        int    $idStarehoUzivatele,
        int    $idNovehoUzivatele,
    ): void {
        // Teď můžeme bezpečně převést zbývající data
        dbQuery(
            "UPDATE `$tabulka` SET `$sloupec` = $idNovehoUzivatele
            WHERE `$sloupec` = $idStarehoUzivatele
                AND NOT EXISTS (
                    SELECT 1 FROM `$tabulka` t2
                    WHERE t2.`$sloupec` = $idNovehoUzivatele
            )",
        );

        // Smažeme co nešlo převést
        dbQuery(
            <<<SQL
            DELETE
            FROM `$tabulka`
            WHERE `$sloupec` = $idStarehoUzivatele
            SQL,
        );

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
