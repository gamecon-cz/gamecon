<?php

declare(strict_types=1);

namespace Gamecon\SystemoveNastaveni;

use Gamecon\Zidle;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

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

        $this->pridejAdminUzivatele($dbConnectionAnonymDb);
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
    }

    private function pridejAdminUzivatele(\mysqli $dbConnectionAnonymDb) {
        $passwordHash = '$2y$10$IudcF5OOSXxvO9I4SK.GBe5AgLhK8IsH7CPBkCknYMhKvJ4HQskzS';
        mysqli_query(
            $dbConnectionAnonymDb,
            <<<SQL
INSERT INTO `{$this->anonymniDatabaze}`.uzivatele_hodnoty
    SET id_uzivatele = null,
        login_uzivatele = 'admin',
        jmeno_uzivatele = 'admin',
        prijmeni_uzivatele = 'adminovec',
        ulice_a_cp_uzivatele = '',
        mesto_uzivatele = '',
        stat_uzivatele = -1,
        psc_uzivatele = '',
        telefon_uzivatele= '',
        datum_narozeni = NOW(),
        heslo_md5 = '$passwordHash',
        funkce_uzivatele = 0,
        email1_uzivatele = 'gamecon@example.com',
        email2_uzivatele = '',
        jine_uzivatele = '',
        nechce_maily = null,
        mrtvy_mail = 0,
        forum_razeni= '',
        random = '',
        zustatek = 0,
        pohlavi = 'm',
        registrovan = NOW(),
        ubytovan_s = '',
        skola = '',
        poznamka = '',
        pomoc_typ = '',
        pomoc_vice= '',
        op ='',
        potvrzeni_zakonneho_zastupce = NULL,
        potvrzeni_proti_covid19_pridano_kdy = NULL,
        potvrzeni_proti_covid19_overeno_kdy=NULL,
        infopult_poznamka= ''
SQL
        );

        $id = mysqli_insert_id($dbConnectionAnonymDb);

        dbQuery(
            "INSERT INTO `{$this->anonymniDatabaze}`.r_uzivatele_zidle (id_uzivatele, id_zidle, posazen, posadil) VALUES ($0, $1, NOW(), null), ($0, $2, NOW(), null)",
            [
                0 => $id,
                1 => Zidle::ORGANIZATOR,
                2 => Zidle::SPRAVCE_FINANCI_GC,
            ],
            $dbConnectionAnonymDb
        );
    }

    private function pridejAdminUzivatele(\mysqli $dbConnectionAnonymDb) {
        dbQuery(<<<SQL
INSERT INTO `{$this->anonymniDatabaze}`.uzivatele_hodnoty
    SET login_uzivatele = 'admin',
        jmeno_uzivatele = 'admin',
        prijmeni_uzivatele = 'adminovec',
        ulice_a_cp_uzivatele = '',
        mesto_uzivatele = '',
        stat_uzivatele = -1,
        psc_uzivatele = '',
        telefon_uzivatele= '',
        datum_narozeni = NOW(),
        heslo_md5 = $0,
        funkce_uzivatele = 0,
        email1_uzivatele = 'gamecon@example.com',
        email2_uzivatele = '',
        jine_uzivatele = '',
        nechce_maily = null,
        mrtvy_mail = 0,
        forum_razeni= '',
        random = '',
        zustatek = 0,
        pohlavi = 'm',
        registrovan = NOW(),
        ubytovan_s = '',
        skola = '',
        poznamka = '',
        pomoc_typ = '',
        pomoc_vice= '',
        op ='',
        potvrzeni_zakonneho_zastupce = NULL,
        potvrzeni_proti_covid19_pridano_kdy = NULL,
        potvrzeni_proti_covid19_overeno_kdy=NULL,
        infopult_poznamka= ''
SQL,
            [
                0 => '$2y$10$IudcF5OOSXxvO9I4SK.GBe5AgLhK8IsH7CPBkCknYMhKvJ4HQskzS',
            ],
            $dbConnectionAnonymDb
        );

        $id = mysqli_insert_id($dbConnectionAnonymDb);

        dbQuery(
            "INSERT INTO `{$this->anonymniDatabaze}`.r_uzivatele_zidle (id_uzivatele, id_zidle, posazen, posadil) VALUES ($0, $1, NOW(), null), ($0, $2, NOW(), null)",
            [
                0 => $id,
                1 => Zidle::ORGANIZATOR,
                2 => Zidle::SPRAVCE_FINANCI_GC,
            ],
            $dbConnectionAnonymDb
        );
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

        // David Grudl je čuně a co na začátku dump souboru vypne, to na konci nezapne
        fwrite(
            $handle,
            <<<SQL
                /*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
                /*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
                /*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
                /*!50503 SET NAMES utf8mb4 */;
                /*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
                /*!40103 SET TIME_ZONE='+00:00' */;
                /*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
                /*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
                /*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
                /*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

            SQL,
        );

        (new \MySQLDump($dbConnectionCurrentDb))->write($handle);

        fwrite(
            $handle,
            <<<SQL

                /*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
                /*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
                /*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
                /*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
                /*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
                /*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
                /*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
                /*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

            SQL
        );

        fflush($handle);
        rewind($handle);

        (new \MySQLImport($dbConnectionAnonymDb))->read($handle);

        fclose($handle);

        foreach (['_vars', 'platby', 'akce_import', 'uzivatele_url'] as $prilisCitlivaTabulka) {
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
