<?php

declare(strict_types=1);

namespace Gamecon\SystemoveNastaveni;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Uzivatel;

class AnonymizovanaDatabaze
{
    private string $anonymniDatabaze;

    public static function vytvorZGlobals(): self {
        global $systemoveNastaveni;
        return new static(\DBM_NAME, \DB_ANONYM_NAME, $systemoveNastaveni);
    }

    private bool $jsmeNaLocale;

    public function __construct(
        string             $soucasnaDatabaze,
        string             $anonymniDatabaze,
        SystemoveNastaveni $systemoveNastaveni,
    ) {
        $this->jsmeNaLocale = $systemoveNastaveni->jsmeNaLocale();
        if ($anonymniDatabaze === $soucasnaDatabaze) {
            throw new \LogicException("Anonymní a současná databáze nemůžou být stejné: '$soucasnaDatabaze'");
        }
        $this->anonymniDatabaze = $anonymniDatabaze;
    }

    public function obnov() {
        $dbConnectionCurrentDb = dbConnect();
        $dbConnectionAnonymDb  = dbConnectionAnonymDb();
        if ($this->jsmeNaLocale) {
            $this->obnovAnonymniDatabazi($dbConnectionAnonymDb);
        }

        $this->zkopirujData($dbConnectionCurrentDb, $dbConnectionAnonymDb);

        $this->anonymizujData($dbConnectionAnonymDb);
    }

    private function anonymizujData(\mysqli $dbConnectionAnonymDb) {
        // Generování nových id a výpočet věku
        //dbQuery('ALTER TABLE `{$this->anonymniDatabaze}`.uzivatele_hodnoty ADD COLUMN vek int');
        //dbQuery('UPDATE `{$this->anonymniDatabaze}`.uzivatele_hodnoty SET vek = TIMESTAMPDIFF(YEAR, datum_narozeni,CURRENT_DATE()), nahoda = RAND() * 1000000000');
        $result = dbQuery(<<<SQL
SELECT COALESCE(MAX(id_uzivatele), 0) FROM `{$this->anonymniDatabaze}`.uzivatele_hodnoty
SQL,
            null,
            $dbConnectionAnonymDb
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
SQL,
                        null,
                        $dbConnectionAnonymDb
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
SQL,
            null,
            $dbConnectionAnonymDb
        );

        dbQuery(<<<SQL
UPDATE `{$this->anonymniDatabaze}`.texty
SET
    `text` = REGEXP_REPLACE(`text`, '[a-zA-Z_0-9.]+@[a-zA-Z_0-9.]+', 'foo@example.com')
SQL,
            null,
            $dbConnectionAnonymDb
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
SQL,
            null,
            $dbConnectionAnonymDb
        );

        dbQuery("UPDATE `{$this->anonymniDatabaze}`.medailonky SET o_sobe = '', drd = ''", null, $dbConnectionAnonymDb);

        dbQuery("ALTER TABLE `{$this->anonymniDatabaze}`.r_uzivatele_zidle MODIFY COLUMN `posazen` TIMESTAMP NULL", null, $dbConnectionAnonymDb);
        dbQuery("ALTER TABLE `{$this->anonymniDatabaze}`.akce_prihlaseni_log MODIFY COLUMN `kdy` TIMESTAMP NULL", null, $dbConnectionAnonymDb);

        dbQuery("INSERT INTO `{$this->anonymniDatabaze}`.uzivatele_hodnoty (login_uzivatele, jmeno_uzivatele, prijmeni_uzivatele, ulice_a_cp_uzivatele, mesto_uzivatele, stat_uzivatele, psc_uzivatele, telefon_uzivatele, datum_narozeni, heslo_md5, funkce_uzivatele, email1_uzivatele, email2_uzivatele, jine_uzivatele, nechce_maily, mrtvy_mail, forum_razeni, random, zustatek, pohlavi, registrovan, ubytovan_s, skola, poznamka, pomoc_typ, pomoc_vice, op, potvrzeni_zakonneho_zastupce, potvrzeni_proti_covid19_pridano_kdy, potvrzeni_proti_covid19_overeno_kdy, infopult_poznamka) VALUES ('admin', 'admin', 'adminovec', 'Na Vyhaslém 3265', 'Kladno', 1, '27201', '736256978', '1983-08-28', '$2y$10$IudcF5OOSXxvO9I4SK.GBe5AgLhK8IsH7CPBkCknYMhKvJ4HQskzS', 0, 'gamecon@example.com', '', '', null, 0, '', '0d336e2ab4cdad85255b', 250, 'm', '2019-05-24 05:52:49', '', null, '', '', '', '', null, '2021-07-14 20:35:00', '2021-07-14 00:00:00', '')", null, $dbConnectionAnonymDb);

        $id = dbQuery("SELECT last_insert_id()", null, $dbConnectionAnonymDb);

        dbQuery("INSERT INTO `{$this->anonymniDatabaze}`.r_uzivatele_zidle (id_uzivatele, id_zidle, posazen, posadil) VALUES (`$id`, -2202, '2022-07-21 13:56:15', null)", null, $dbConnectionAnonymDb);
        dbQuery("INSERT INTO `{$this->anonymniDatabaze}`.r_uzivatele_zidle (id_uzivatele, id_zidle, posazen, posadil) VALUES (`$id`, -2201, '2022-07-21 13:56:16', null)", null, $dbConnectionAnonymDb);
        dbQuery("INSERT INTO `{$this->anonymniDatabaze}`.r_uzivatele_zidle (id_uzivatele, id_zidle, posazen, posadil) VALUES (`$id`, -2202, '2022-07-21 13:56:17', null)", null, $dbConnectionAnonymDb);
        dbQuery("INSERT INTO `{$this->anonymniDatabaze}`.r_uzivatele_zidle (id_uzivatele, id_zidle, posazen, posadil) VALUES (`$id`, -2201, '2022-07-21 13:56:18', null)", null, $dbConnectionAnonymDb);
        dbQuery("INSERT INTO `{$this->anonymniDatabaze}`.r_uzivatele_zidle (id_uzivatele, id_zidle, posazen, posadil) VALUES (`$id`, 2, '2022-07-21 13:56:19', null)", null, $dbConnectionAnonymDb);
        dbQuery("INSERT INTO `{$this->anonymniDatabaze}`.r_uzivatele_zidle (id_uzivatele, id_zidle, posazen, posadil) VALUES (`$id`, 20, '2022-07-21 13:56:20', null)", null, $dbConnectionAnonymDb);
        
    }

    private function obnovAnonymniDatabazi(\mysqli $dbConnectionAnonymDb) {
        dbQuery(<<<SQL
DROP DATABASE IF EXISTS `{$this->anonymniDatabaze}`
SQL
            ,
            null,
            $dbConnectionAnonymDb
        );
        dbQuery(<<<SQL
CREATE DATABASE `{$this->anonymniDatabaze}` DEFAULT CHARACTER SET utf8 COLLATE utf8_czech_ci
SQL,
            null,
            $dbConnectionAnonymDb
        );
        dbQuery(<<<SQL
USE `{$this->anonymniDatabaze}`
SQL,
            null,
            $dbConnectionAnonymDb
        );
    }

    private function zkopirujData(
        \mysqli $dbConnectionCurrentDb,
        \mysqli $dbConnectionAnonymDb
    ) {
        $handle = fopen('php://memory', 'r+b');

        (new \MySQLDump($dbConnectionCurrentDb))->write($handle);

        fflush($handle);
        rewind($handle);

        (new \MySQLImport($dbConnectionAnonymDb))->read($handle);

        fclose($handle);

        foreach (['_vars', 'platby', 'akce_import'] as $prilisCitlivaTabulka) {
            dbQuery(<<<SQL
DELETE FROM $prilisCitlivaTabulka
SQL,
                null,
                $dbConnectionAnonymDb
            );
        }
    }

    public function exportuj() {
        $tempFile = tempnam(sys_get_temp_dir(), 'anonymizovana_databaze');
        (new \MySQLDump(dbConnectionAnonymDb()))->save($tempFile);
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
