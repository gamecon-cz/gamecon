<?php

declare(strict_types=1);

namespace Gamecon\SystemoveNastaveni;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class AnonymizovanaDatabaze
{
    private string $anonymniDatabaze;

    public static function vytvorZGlobals(): self {
        global $systemoveNastaveni;
        return new static(DBM_NAME, $systemoveNastaveni);
    }

    private bool $jsmeNaLocale;
    private string $soucasnaDatabaze;

    public function __construct(
        string             $soucasnaDatabaze,
        SystemoveNastaveni $systemoveNastaveni,
        string             $anonymniDatabaze = 'anonymni_databaze'
    ) {
        $this->jsmeNaLocale = $systemoveNastaveni->jsmeNaLocale();
        if ($anonymniDatabaze === $soucasnaDatabaze) {
            throw new \LogicException("Anonymní a současná databáze nemůžou nýt stejné: '$soucasnaDatabaze'");
        }
        $this->soucasnaDatabaze = $soucasnaDatabaze;
        $this->anonymniDatabaze = $anonymniDatabaze;
    }

    public function obnov() {
        dbConnectWithAlterPermissions(true, true);

        if ($this->jsmeNaLocale) {
            $this->obnovAnonymniDatabazi();
        }

        $this->zkopirujTabulky();

        $this->zkopirujData();

        $this->anonymizujData();

    }

    private function anonymizujData() {
        // Generování nových id a výpočet věku
        //dbQuery('ALTER TABLE `{$this->anonymniDatabaze}`.uzivatele_hodnoty ADD COLUMN vek int');
        //dbQuery('UPDATE `{$this->anonymniDatabaze}`.uzivatele_hodnoty SET vek = TIMESTAMPDIFF(YEAR, datum_narozeni,CURRENT_DATE()), nahoda = RAND() * 1000000000');
        $result = dbQuery(<<<SQL
SELECT MAX(id_uzivatele) FROM `{$this->anonymniDatabaze}`.uzivatele_hodnoty
SQL
        );
        $maxId  = mysqli_fetch_column($result);

        // Anonymizace ID uživatele
        $remainingAttempts = 20;
        do {
            $dbException = null;
            try {
                do {
                    $updateIdUzivateleResult = dbQuery(<<<SQL
UPDATE `{$this->anonymniDatabaze}`.uzivatele_hodnoty
SET id_uzivatele = (SELECT $maxId + CAST(FLOOR(RAND() * 10000000) AS UNSIGNED))
WHERE id_uzivatele <= $maxId
LIMIT 100 -- nutno dávkovat, jinak to způsobí Duplicate entry 'X' for key 'PRIMARY'
SQL
                    );
                } while (dbNumRows($updateIdUzivateleResult) > 0);
            } catch (\DbException $dbException) {
                $remainingAttempts--;
            }
        } while ($dbException && $remainingAttempts > 0);
        if ($dbException && $remainingAttempts <= 0) {
            throw new \RuntimeException(
                "Ani po několika pokusech se nepodařilo změnit všechna ID uživatelů: " . $dbException->getMessage(),
                $dbException->getCode(),
                $dbException
            );
        }

        dbQuery(<<<SQL
UPDATE `{$this->anonymniDatabaze}`.stranky
SET
    obsah = REGEXP_REPLACE(obsah, '[a-zA-Z_0-9.]+@[a-zA-Z_0-9.]+', 'foo@example.com')
SQL
        );

        dbQuery(<<<SQL
UPDATE `{$this->anonymniDatabaze}`.texty
SET
    `text` = REGEXP_REPLACE(`text`, '[a-zA-Z_0-9.]+@[a-zA-Z_0-9.]+', 'foo@example.com')
SQL
        );

        dbQuery(<<<SQL
UPDATE `{$this->anonymniDatabaze}`.uzivatele_hodnoty
SET
login_uzivatele = CONCAT('Login', id_uzivatele),
jmeno_uzivatele = '',
prijmeni_uzivatele = '',
ulice_a_cp_uzivatele = '',
mesto_uzivatele = '',
stat_uzivatele = -1,
psc_uzivatele = '',
telefon_uzivatele = '',
datum_narozeni = '0000-01-01',
heslo_md5 = '',
email1_uzivatele = CONCAT('email', id_uzivatele, '@gamecon.cz'),
email2_uzivatele = '',
jine_uzivatele = '',
nechce_maily = null,
mrtvy_mail = 0,
forum_razeni = '',
random = '',
zustatek = 0,
registrovan = NOW(),
ubytovan_s = '',
skola = '',
poznamka = '',
pomoc_typ = '',
pomoc_vice = '',
op = '',
potvrzeni_zakonneho_zastupce = null,
potvrzeni_proti_covid19_pridano_kdy = null,
potvrzeni_proti_covid19_overeno_kdy = null,
infopult_poznamka = ''
SQL
        );

        dbQuery("UPDATE `{$this->anonymniDatabaze}`.medailonky SET o_sobe = '', drd = ''");

        dbQuery("ALTER TABLE `{$this->anonymniDatabaze}`.r_uzivatele_zidle MODIFY COLUMN `posazen` TIMESTAMP NULL");
        dbQuery("ALTER TABLE `{$this->anonymniDatabaze}`.akce_prihlaseni_log MODIFY COLUMN `kdy` TIMESTAMP NULL");
    }

    private function obnovAnonymniDatabazi() {
        dbQuery(<<<SQL
DROP DATABASE IF EXISTS `{$this->anonymniDatabaze}`
SQL
        );
        dbQuery(<<<SQL
CREATE DATABASE `{$this->anonymniDatabaze}` DEFAULT CHARACTER SET utf8 COLLATE utf8_czech_ci
SQL
        );
    }

    private function zkopirujTabulky() {
        $this->zkopirujStrukturuTabulekBezCizichKlicu();
        $this->naklonujStrukturuTabulekVcetneCicichKlicu();
    }

    private function zkopirujStrukturuTabulekBezCizichKlicu() {
        foreach ($this->nazvyOriginalnichTabulek() as $tabulka) {
            dbQuery(<<<SQL
DROP TABLE IF EXISTS `{$this->anonymniDatabaze}`.`{$tabulka}`
SQL
            );
            // vytvoří se bez cizích klíčů
            dbQuery(<<<SQL
CREATE TABLE `{$this->anonymniDatabaze}`.`{$tabulka}` LIKE `{$this->soucasnaDatabaze}`.`{$tabulka}`
SQL
            );
        }
    }

    // teprve když všechny tabulky existují, můžeme vytvářet cizí klíče
    private function naklonujStrukturuTabulekVcetneCicichKlicu() {
        foreach ($this->nazvyOriginalnichTabulek() as $tabulka) {
            $tableDefinitionWrapped = dbFetchPairs(<<<SQL
SHOW CREATE TABLE `{$this->soucasnaDatabaze}`.`{$tabulka}`
SQL
            );
            $tableDefinition        = reset($tableDefinitionWrapped);
            if (preg_match_all('~(?<fk>.*FOREIGN KEY.*)~', $tableDefinition, $foreignKeysMatches)) {
                $addForeignKeys    = array_map(static function (string $foreignKeyDefinition) {
                    return "ADD $foreignKeyDefinition";
                }, $foreignKeysMatches['fk']);
                $addForeignKeysSql = implode("\n", $addForeignKeys);
                dbQuery(<<<SQL
ALTER TABLE `{$this->anonymniDatabaze}`.`{$tabulka}`
    $addForeignKeysSql
SQL
                );
            }
        }
    }

    private function nazvyOriginalnichTabulek(): array {
        static $nazvyOriginalnichTabulek;
        if (!$nazvyOriginalnichTabulek) {
            $nazvyOriginalnichTabulek = dbFetchColumn(<<<SQL
SHOW TABLES FROM `{$this->soucasnaDatabaze}`
SQL
            );
        }
        return $nazvyOriginalnichTabulek;
    }

    private function zkopirujData() {
        foreach ($this->nazvyOriginalnichTabulek() as $tabulka) {
            if (in_array($tabulka, ['platby', 'akce_import'], true)) {
                continue;
            }
            dbQuery(<<<SQL
SET FOREIGN_KEY_CHECKS=0
SQL
            );
            dbQuery(<<<SQL
INSERT INTO `{$this->anonymniDatabaze}`.`{$tabulka}` SELECT * FROM `{$this->soucasnaDatabaze}`.`{$tabulka}`
SQL
            );
            dbQuery(<<<SQL
SET FOREIGN_KEY_CHECKS=1
SQL
            );
        }
    }

    public function exportuj() {
        $connection = dbConnect();
        dbQuery(<<<SQL
USE `$this->anonymniDatabaze`
SQL
        );
        $tempFile = tempnam(sys_get_temp_dir(), 'anonymizovana_databaze');
        (new \MySQLDump($connection))->save($tempFile);
        $request  = new Request();
        $response = (new BinaryFileResponse($tempFile));
        $response->headers->set('Content-Type', 'application/sql');
        $response->deleteFileAfterSend()
            ->setContentDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                'gc_anonymizovana_databaze_' . date('Y-m-d_h-i-s') . '.sql'
            )
            ->prepare($request)
            ->send();
    }
}
