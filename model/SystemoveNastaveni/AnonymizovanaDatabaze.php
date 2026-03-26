<?php

declare(strict_types=1);

namespace Gamecon\SystemoveNastaveni;

use Gamecon\Role\Role;
use Gamecon\Uzivatel\AnonymizovanyUzivatel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class AnonymizovanaDatabaze
{
    public const ADMIN_LOGIN    = 'admin';
    public const ADMIN_PASSWORD = 'admin';

    public static function vytvorZGlobals(): self
    {
        global $systemoveNastaveni;

        return new static(
            DB_NAME,
            DB_ANONYM_NAME,
            $systemoveNastaveni,
            new NastrojeDatabaze($systemoveNastaveni),
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

    public function obnov(?\PDO $dbConnectionAnonymDb = null): void
    {
        $dbConnectionAnonymDb = $dbConnectionAnonymDb ?? dbConnectionAnonymDb();

        $this->obnovAnonymniDatabazi($dbConnectionAnonymDb);

        $this->zkopirujData($dbConnectionAnonymDb);

        $this->anonymizujData($dbConnectionAnonymDb);

        if (func_num_args() === 0) {
            $dbConnectionAnonymDb = dbConnectionAnonymDb(); // nevím proč, ale pokud použiju předchozí connection, tak se admin uživatel přidá někam do voidu
        }
        $this->pridejAdminUzivatele($dbConnectionAnonymDb);
    }

    private function anonymizujData(\PDO $dbConnectionAnonymDb)
    {
        $db = $this->anonymniDatabaze;
        $systemovyUzivatelId = \Uzivatel::SYSTEM;

        // Anonymizace ID uživatele — deterministický posun místo náhodného, aby nedocházelo ke kolizím
        $offset = random_int(1_000_000, 9_000_000);
        $dbConnectionAnonymDb->query("SET FOREIGN_KEY_CHECKS = 0");
        $dbConnectionAnonymDb->query(<<<SQL
                UPDATE `{$db}`.uzivatele_hodnoty
                SET id_uzivatele = id_uzivatele + $offset
                WHERE id_uzivatele != $systemovyUzivatelId
            SQL,
        );
        $dbConnectionAnonymDb->query("SET FOREIGN_KEY_CHECKS = 1");

        // Kaskádní aktualizace ID ve všech závislých tabulkách
        $fkResult = $dbConnectionAnonymDb->query(<<<SQL
            SELECT TABLE_NAME, COLUMN_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = '{$db}'
                AND REFERENCED_TABLE_NAME = 'uzivatele_hodnoty'
                AND REFERENCED_COLUMN_NAME = 'id_uzivatele'
        SQL);
        while ($fk = $fkResult->fetch(\PDO::FETCH_ASSOC)) {
            $dbConnectionAnonymDb->query(
                "UPDATE `{$db}`.`{$fk['TABLE_NAME']}` SET `{$fk['COLUMN_NAME']}` = `{$fk['COLUMN_NAME']}` + $offset WHERE `{$fk['COLUMN_NAME']}` != $systemovyUzivatelId",
            );
        }

        $dbConnectionAnonymDb->query(<<<SQL
                UPDATE `{$db}`.stranky
                SET obsah = REGEXP_REPLACE(obsah, '[a-zA-Z_0-9.]+@[a-zA-Z_0-9.]+', 'foo@example.com')
                WHERE TRUE
            SQL,
        );

        $dbConnectionAnonymDb->query(<<<SQL
                UPDATE `{$db}`.uzivatele_hodnoty
                SET {$this->sqlSetProAnonymizaciUzivatele()}
                WHERE TRUE
            SQL,
        );

        $medailonkyData = AnonymizovanyUzivatel::vytvorAnonymniMedailonkoveDaje();
        $medailonkySet  = implode(', ', array_map(
            fn($key, $value) => "$key = '$value'",
            array_keys($medailonkyData),
            array_values($medailonkyData),
        ));
        $dbConnectionAnonymDb->query("UPDATE `{$db}`.`medailonky` SET $medailonkySet WHERE TRUE");

        $dbConnectionAnonymDb->query("UPDATE `{$db}`.uzivatele_role SET `posazen` = '1970-01-01 01:01:01' WHERE TRUE");
        $dbConnectionAnonymDb->query("UPDATE `{$db}`.akce_prihlaseni_log SET `kdy` = '1970-01-01 01:01:01' WHERE TRUE");
    }

    private function pridejAdminUzivatele(\PDO $dbConnectionAnonymDb)
    {
        // na toto heslo nespoléhat - raději použít konstantu UNIVERZALNI_HESLO
        $passwordHash = password_hash(self::ADMIN_PASSWORD, PASSWORD_DEFAULT);
        $adminLogin   = self::ADMIN_LOGIN;
        $dbConnectionAnonymDb->query(<<<SQL
INSERT INTO `{$this->anonymniDatabaze}`.uzivatele_hodnoty
    SET id_uzivatele = null,
        login_uzivatele = '{$adminLogin}',
        jmeno_uzivatele = '{$adminLogin}',
        prijmeni_uzivatele = 'adminovec',
        ulice_a_cp_uzivatele = '',
        mesto_uzivatele = '',
        stat_uzivatele = -1,
        psc_uzivatele = '',
        telefon_uzivatele= '',
        datum_narozeni = NOW(),
        heslo_md5 = '$passwordHash',
        email1_uzivatele = '{$adminLogin}.gamecon@example.com',
        nechce_maily = null,
        mrtvy_mail = 0,
        forum_razeni= '',
        random = '',
        zustatek = 0,
        pohlavi = 'm',
        registrovan = NOW(),
        ubytovan_s = '',
        poznamka = '',
        pomoc_typ = '',
        pomoc_vice= '',
        op ='',
        potvrzeni_zakonneho_zastupce = NULL,
        infopult_poznamka= ''
SQL,
        );

        $id = $dbConnectionAnonymDb->lastInsertId();

        $idRoleOrganizator    = Role::ORGANIZATOR;
        $idRoleSpravceFinanci = Role::CFO;
        $dbConnectionAnonymDb->query(
            "INSERT INTO `{$this->anonymniDatabaze}`.uzivatele_role (id_uzivatele, id_role, posazen, posadil) VALUES ($id, $idRoleOrganizator, NOW(), null), ($id, $idRoleSpravceFinanci, NOW(), null)",
        );
    }

    private function sqlSetProAnonymizaciUzivatele(): string
    {
        return AnonymizovanyUzivatel::sqlSetProAnonymizaci();
    }

    private function obnovAnonymniDatabazi(\PDO $dbConnectionAnonymDb): void
    {
        if ($this->jsmeNaLocale) {
            $this->smazVytvorAnonymniDatabazi($dbConnectionAnonymDb);
        } else {
            $this->vycistiAnonymniDatabazi($dbConnectionAnonymDb);
        }
    }

    private function smazVytvorAnonymniDatabazi(\PDO $dbConnectionAnonymDb): void
    {
        $dbConnectionAnonymDb->query(<<<SQL
                DROP DATABASE IF EXISTS `{$this->anonymniDatabaze}`
            SQL,
        );
        $dbConnectionAnonymDb->query(<<<SQL
                CREATE DATABASE `{$this->anonymniDatabaze}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci
            SQL,
        );
        $dbConnectionAnonymDb->query(<<<SQL
                USE `{$this->anonymniDatabaze}`
            SQL,
        );
    }

    private function vycistiAnonymniDatabazi(\PDO $dbConnectionAnonymDb): void
    {
        $this->nastrojeDatabaze->vymazVseZDatabaze($this->anonymniDatabaze, $dbConnectionAnonymDb);
    }

    private function zkopirujData(
        \PDO $dbConnectionAnonymDb,
    ): void {
        $tempFile = tempnam(sys_get_temp_dir(), 'anonymizovana_databaze_');
        /*
        * DEFINER vyžaduje SUPER privileges https://stackoverflow.com/questions/44015692/access-denied-you-need-at-least-one-of-the-super-privileges-for-this-operat
        * ale nás definer nezajímá, tak ho zahodíme
        */
        $mysqldump = $this->nastrojeDatabaze->vytvorMysqldumpHlavniDatabaze([
            'skip-definer' => true,
        ]);
        $mysqldump->start($tempFile);
        NastrojeDatabaze::removeDefiners($tempFile);

        $mysqliConnection = dbConnectMysqli(DB_ANONYM_SERV, DB_ANONYM_USER, DB_ANONYM_PASS, null, $this->anonymniDatabaze);
        (new \MySQLImport($mysqliConnection))->load($tempFile);
        mysqli_close($mysqliConnection);

        $dbConnectionAnonymDb->query("SET FOREIGN_KEY_CHECKS = 0");
        foreach (['_vars', 'platby', 'akce_import', 'uzivatele_url'] as $prilisCitlivaTabulka) {
            $dbConnectionAnonymDb->query("TRUNCATE TABLE `{$this->anonymniDatabaze}`.`$prilisCitlivaTabulka`");
        }
        $dbConnectionAnonymDb->query("SET FOREIGN_KEY_CHECKS = 1");
    }

    public static function cestaExportu(): string
    {
        return ZALOHA_DB_SLOZKA . '/gc_anonymizovana_databaze.sql.gz';
    }

    public static function datumPoslednihoExportu(): ?\DateTimeImmutable
    {
        $cesta = self::cestaExportu();
        if (!file_exists($cesta)) {
            return null;
        }
        return (new \DateTimeImmutable())->setTimestamp(filemtime($cesta));
    }

    public function exportujDoSouboru(): void
    {
        $cesta = self::cestaExportu();
        if (!is_dir(dirname($cesta))) {
            mkdir(dirname($cesta), 0750, true);
        }
        $tempSqlFile = tempnam(sys_get_temp_dir(), 'anonymizovana_databaze_');
        $mysqldump = $this->nastrojeDatabaze->vytvorMysqldumpAnonymniDatabaze([
            'skip-definer'     => true,
            'add-drop-table'   => true,
            'add-drop-trigger' => true,
        ]);
        $mysqldump->start($tempSqlFile);
        NastrojeDatabaze::removeDefiners($tempSqlFile);

        $tempGzFile = $cesta . '.tmp';
        $sqlContent = file_get_contents($tempSqlFile);
        file_put_contents($tempGzFile, gzencode($sqlContent, 9));
        unlink($tempSqlFile);
        rename($tempGzFile, $cesta);
    }

    public function exportuj(): void
    {
        $cesta = self::cestaExportu();
        if (!file_exists($cesta)) {
            throw new \RuntimeException('Anonymizovaná databáze ještě nebyla vygenerována');
        }
        $request  = new Request();
        $response = (new BinaryFileResponse($cesta));
        $response->headers->set('Content-Type', 'application/gzip');
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'gc_anonymizovana_databaze.sql.gz',
        )
                 ->prepare($request)
                 ->send();
    }
}
