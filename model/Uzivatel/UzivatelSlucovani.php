<?php

namespace Gamecon\Uzivatel;

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

        // Zachycení původních hodnot pro logování
        $zustatekStarehoUzivatele = $staryUzivatel->finance()->zustatekZPredchozichRocniku();
        $zustatekNovehoUzivatele = $novyUzivatel->finance()->zustatekZPredchozichRocniku();
        $emailStarehoUzivatele = $staryUzivatel->mail();
        $emailNovehoUzivatele = $novyUzivatel->mail();

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
        $novyUzivatel = Uzivatel::zId($novyUzivatel->id()); // přenačtení uživatele, aby se aktualizovaly finance
        $this->zaloguj(
            $idStarehoUzivatele,
            $idNovehoUzivatele,
            $zustatekStarehoUzivatele,
            $zustatekNovehoUzivatele,
            $emailStarehoUzivatele,
            $emailNovehoUzivatele,
            $novyUzivatel->finance()->zustatekZPredchozichRocniku(),
            $novyUzivatel->mail()
        );
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
     *
     * Ke 22. 9. 2025 to byly tabulky:
     * ubytovani
     * akce_organizatori
     * shop_nakupy
     * uzivatele_role_podle_rocniku
     * akce_import
     * uzivatele_role
     * google_api_user_tokens
     * akce_seznam
     * akce_prihlaseni_log
     * uzivatele_url
     * log_udalosti
     * platby
     * shop_nakupy_zrusene
     * reporty_log_pouziti
     * akce_prihlaseni_spec
     * akce_prihlaseni
     * medailonky
     * hromadne_akce_log
     * uzivatele_role_log
     * mutex
     * google_drive_dirs
     * systemove_nastaveni_log
     * role_texty_podle_uzivatele
     * slevy
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
     * Zapíše záznam o slučování uživatelů do databáze.
     */
    private function zaloguj(
        int    $idSmazanehoUzivatele,
        int    $idNovehoUzivatele,
        int    $zustatekSmazanehoPuvodne,
        int    $zustatekNovehoPuvodne,
        string $emailSmazaneho,
        string $emailNovehoPuvodne,
        int    $zustatekNovehoAktualne,
        string $emailNovehoAktualne,
    ): void {
        dbQuery(<<<SQL
INSERT INTO uzivatele_slucovani_log (
    id_smazaneho_uzivatele,
    id_noveho_uzivatele,
    zustatek_smazaneho_puvodne,
    zustatek_noveho_puvodne,
    email_smazaneho,
    email_noveho_puvodne,
    zustatek_noveho_aktualne,
    email_noveho_aktualne
) VALUES ($1, $2, $3, $4, $5, $6, $7, $8)
SQL,
            [
                1 => $idSmazanehoUzivatele,
                2 => $idNovehoUzivatele,
                3 => $zustatekSmazanehoPuvodne,
                4 => $zustatekNovehoPuvodne,
                5 => $emailSmazaneho,
                6 => $emailNovehoPuvodne,
                7 => $zustatekNovehoAktualne,
                8 => $emailNovehoAktualne,
            ]
        );
    }
}
