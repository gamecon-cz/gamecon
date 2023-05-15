<?php

declare(strict_types=1);

namespace Gamecon\SystemoveNastaveni;

use Gamecon\Role\Role;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class AnonymizovanaDatabaze
{
    public static function vytvorZGlobals(): self {
        global $systemoveNastaveni;
        return new static(
            \DBM_NAME,
            \DB_ANONYM_NAME,
            $systemoveNastaveni,
            new NastrojeDatabaze($systemoveNastaveni)
        );
    }

    private bool $jsmeNaLocale;

    public function __construct(
        string                   $zdrojovaDatabaze,
        private string           $anonymniDatabaze,
        SystemoveNastaveni       $systemoveNastaveni,
        private NastrojeDatabaze $nastrojeDatabaze,
    ) {
        $this->jsmeNaLocale = $systemoveNastaveni->jsmeNaLocale();
        if ($anonymniDatabaze === $zdrojovaDatabaze) {
            throw new \LogicException("Anonymní a současná databáze nemůžou být stejné: '$zdrojovaDatabaze'");
        }
    }

    public function obnov() {
        $dbConnectionAnonymDb = dbConnectionAnonymDb();

        $this->obnovAnonymniDatabazi($dbConnectionAnonymDb);

        $this->zkopirujData($dbConnectionAnonymDb);

        $this->anonymizujData($dbConnectionAnonymDb);

        $dbConnectionAnonymDb = dbConnectionAnonymDb(); // nevím proč, ale pokud použiju předchozí connection, tak se admin uživatel přidá někam do voidu
        $this->pridejAdminUzivatele($dbConnectionAnonymDb);
    }

    private function anonymizujData(\mysqli $dbConnectionAnonymDb) {
        $result = mysqli_query(
            $dbConnectionAnonymDb,
            <<<SQL
                SELECT COALESCE(MAX(id_uzivatele), 0) FROM `{$this->anonymniDatabaze}`.uzivatele_hodnoty
            SQL
        );
        $maxId  = mysqli_fetch_column($result);

        // Anonymizace ID uživatele
        $remainingAttempts = 20;
        do {
            $dbException = null;
            try {
                do {
                    $updateIdUzivateleResult = mysqli_query(
                        $dbConnectionAnonymDb,
                        <<<SQL
                            UPDATE `{$this->anonymniDatabaze}`.uzivatele_hodnoty
                            SET id_uzivatele = (SELECT $maxId + CAST(FLOOR(RAND() * 10000000) AS UNSIGNED))
                            WHERE id_uzivatele <= $maxId
                            LIMIT 100 -- nutno dávkovat, jinak to způsobí Duplicate entry 'X' for key 'PRIMARY'
                        SQL
                    );
                } while ($updateIdUzivateleResult && mysqli_affected_rows($dbConnectionAnonymDb) > 0);
            } catch (\mysqli_sql_exception $dbException) {
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

        mysqli_query(
            $dbConnectionAnonymDb,
            <<<SQL
                UPDATE `{$this->anonymniDatabaze}`.stranky
                SET
                    obsah = REGEXP_REPLACE(obsah, '[a-zA-Z_0-9.]+@[a-zA-Z_0-9.]+', 'foo@example.com')
            SQL
        );

        mysqli_query(
            $dbConnectionAnonymDb,
            <<<SQL
                UPDATE `{$this->anonymniDatabaze}`.texty
                SET
                    `text` = REGEXP_REPLACE(`text`, '[a-zA-Z_0-9.]+@[a-zA-Z_0-9.]+', 'foo@example.com')
            SQL
        );

        mysqli_query(
            $dbConnectionAnonymDb,
            <<<SQL
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

        mysqli_query(
            $dbConnectionAnonymDb,
            "UPDATE `{$this->anonymniDatabaze}`.medailonky SET o_sobe = '', drd = ''"
        );

        mysqli_query(
            $dbConnectionAnonymDb,
            "ALTER TABLE `{$this->anonymniDatabaze}`.uzivatele_role MODIFY COLUMN `posazen` TIMESTAMP NULL"
        );
        mysqli_query(
            $dbConnectionAnonymDb,
            "ALTER TABLE `{$this->anonymniDatabaze}`.akce_prihlaseni_log MODIFY COLUMN `kdy` TIMESTAMP NULL"
        );
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

        $idRoleOrganizator    = Role::ORGANIZATOR;
        $idRoleSpravceFinanci = Role::CFO;
        mysqli_query(
            $dbConnectionAnonymDb,
            "INSERT INTO `{$this->anonymniDatabaze}`.uzivatele_role (id_uzivatele, id_role, posazen, posadil) VALUES ($id, $idRoleOrganizator, NOW(), null), ($id, $idRoleSpravceFinanci, NOW(), null)"
        );
    }

    private function obnovAnonymniDatabazi(\mysqli $dbConnectionAnonymDb) {
        if ($this->jsmeNaLocale) {
            $this->smazVytvorAnonymniDatabazi($dbConnectionAnonymDb);
        } else {
            $this->vycistiAnonymniDatabazi($dbConnectionAnonymDb);
        }
    }

    private function smazVytvorAnonymniDatabazi(\mysqli $dbConnectionAnonymDb) {
        mysqli_query(
            $dbConnectionAnonymDb,
            <<<SQL
                DROP DATABASE IF EXISTS `{$this->anonymniDatabaze}`
            SQL
        );
        mysqli_query(
            $dbConnectionAnonymDb,
            <<<SQL
                CREATE DATABASE `{$this->anonymniDatabaze}` DEFAULT CHARACTER SET utf8 COLLATE utf8_czech_ci
            SQL
        );
        mysqli_query(
            $dbConnectionAnonymDb,
            <<<SQL
                USE `{$this->anonymniDatabaze}`
            SQL
        );
    }

    private function vycistiAnonymniDatabazi(\mysqli $dbConnectionAnonymDb) {
        $this->nastrojeDatabaze->vymazVseZDatabaze($this->anonymniDatabaze, $dbConnectionAnonymDb);
    }

    private function zkopirujData(
        \mysqli $dbConnectionAnonymDb
    ) {
        $tempFile = tempnam(sys_get_temp_dir(), 'anonymizovana_databaze_');
        /*
        * DEFINER vyžaduje SUPER privileges https://stackoverflow.com/questions/44015692/access-denied-you-need-at-least-one-of-the-super-privileges-for-this-operat
        * ale nás definer nezajímá, tak ho zahodíme
        */
        $mysqldump = $this->nastrojeDatabaze->vytvorMysqldumpHlavniDatabaze([
            'skip-definer' => true,
        ]);
        $mysqldump->start($tempFile);

        (new \MySQLImport($dbConnectionAnonymDb))->load($tempFile);

        foreach (['_vars', 'platby', 'akce_import', 'uzivatele_url'] as $prilisCitlivaTabulka) {
            mysqli_query(
                $dbConnectionAnonymDb,
                <<<SQL
                    DELETE FROM $prilisCitlivaTabulka
                    WHERE TRUE
                SQL
            );
        }
    }

    public function exportuj() {
        $tempFile = tempnam(sys_get_temp_dir(), 'anonymizovana_databaze_');
        /*
        * DEFINER vyžaduje SUPER privileges https://stackoverflow.com/questions/44015692/access-denied-you-need-at-least-one-of-the-super-privileges-for-this-operat
        * ale nás definer nezajímá, tak ho zahodíme
        */
        $mysqldump = $this->nastrojeDatabaze->vytvorMysqldumpAnonymniDatabaze([
            'skip-definer'     => true,
            'add-drop-table'   => true,
            'add-drop-trigger' => true,
        ]);
        $mysqldump->start($tempFile);
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
