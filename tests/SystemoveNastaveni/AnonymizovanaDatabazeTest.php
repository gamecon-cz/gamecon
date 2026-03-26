<?php

declare(strict_types=1);

namespace Gamecon\Tests\SystemoveNastaveni;

use Gamecon\Role\Role;
use Gamecon\SystemoveNastaveni\AnonymizovanaDatabaze;
use Gamecon\SystemoveNastaveni\NastrojeDatabaze;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\ZpusobZobrazeniNaWebu;

class AnonymizovanaDatabazeTest extends AbstractTestDb
{
    protected static bool $disableStrictTransTables = true;

    private static string $anonymniDatabaze;

    protected static function keepTestClassDbChangesInTransaction(): bool
    {
        return false;
    }

    protected static function keepSingleTestMethodDbChangesInTransaction(): bool
    {
        return false;
    }

    protected static function resetDbAfterClass(): bool
    {
        return true;
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$anonymniDatabaze = DB_NAME . '_anonym';

        dbQuery("INSERT INTO uzivatele_hodnoty SET
            id_uzivatele = 100,
            login_uzivatele = 'testuser',
            jmeno_uzivatele = 'Jan',
            prijmeni_uzivatele = 'Novák',
            ulice_a_cp_uzivatele = 'Ulice 1',
            mesto_uzivatele = 'Praha',
            stat_uzivatele = 1,
            psc_uzivatele = '11000',
            telefon_uzivatele = '+420123456789',
            datum_narozeni = '1990-01-01',
            heslo_md5 = 'hash',
            email1_uzivatele = 'jan.novak@example.com',
            mrtvy_mail = 0,
            forum_razeni = '',
            random = '',
            zustatek = 0,
            pohlavi = 'm',
            registrovan = NOW(),
            ubytovan_s = '',
            poznamka = '',
            pomoc_typ = '',
            pomoc_vice = '',
            op = '',
            zpusob_zobrazeni_na_webu = 2,
            infopult_poznamka = ''
        ");

        dbQuery("INSERT INTO medailonky SET id_uzivatele = 100, o_sobe = 'Osobní info', drd = 'DRD info'");

        dbQuery("INSERT INTO stranky SET url_stranky = 'test', obsah = 'Kontakt: jan.novak@example.com', poradi = 1");
    }

    public static function tearDownAfterClass(): void
    {
        $connection = dbConnectTemporary();
        dbQuery(sprintf('DROP DATABASE IF EXISTS `%s`', self::$anonymniDatabaze), null, $connection);
        parent::tearDownAfterClass();
    }

    public function testObnovVytvoriFunkcniAnonymniDatabazi(): void
    {
        $systemoveNastaveni = SystemoveNastaveni::zGlobals();
        $nastrojeDatabaze = new NastrojeDatabaze($systemoveNastaveni);

        $anonymizovanaDatabaze = new AnonymizovanaDatabaze(
            DB_NAME,
            self::$anonymniDatabaze,
            $systemoveNastaveni,
            $nastrojeDatabaze,
        );

        $connection = dbConnectionAnonymDb();
        $connection->query(sprintf('DROP DATABASE IF EXISTS `%s`', self::$anonymniDatabaze));
        $connection->query(sprintf('CREATE DATABASE `%s` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci', self::$anonymniDatabaze));
        $connection->query(sprintf('USE `%s`', self::$anonymniDatabaze));

        $anonymizovanaDatabaze->obnov($connection);

        // anonymous DB should have the tables
        $tablesResult = $connection->query(sprintf("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '%s'", self::$anonymniDatabaze));
        $tables = array_column($tablesResult->fetchAll(\PDO::FETCH_ASSOC), 'TABLE_NAME');
        self::assertContains('uzivatele_hodnoty', $tables, 'Anonymní databáze by měla obsahovat tabulku uzivatele_hodnoty');
        self::assertContains('stranky', $tables, 'Anonymní databáze by měla obsahovat tabulku stranky');
        self::assertContains('medailonky', $tables, 'Anonymní databáze by měla obsahovat tabulku medailonky');

        // sensitive tables should be empty
        foreach (['_vars', 'platby', 'akce_import', 'uzivatele_url'] as $sensitivniTabulka) {
            $countResult = $connection->query(sprintf('SELECT COUNT(*) as cnt FROM `%s`.`%s`', self::$anonymniDatabaze, $sensitivniTabulka));
            $count = $countResult->fetch(\PDO::FETCH_ASSOC)['cnt'];
            self::assertSame('0', $count, "Tabulka {$sensitivniTabulka} by měla být prázdná");
        }

        // user ID should be anonymized (different from original 100)
        $originalIdResult = $connection->query(sprintf(
            'SELECT COUNT(*) as cnt FROM `%s`.uzivatele_hodnoty WHERE id_uzivatele = 100',
            self::$anonymniDatabaze,
        ));
        self::assertSame('0', $originalIdResult->fetch(\PDO::FETCH_ASSOC)['cnt'], 'Původní ID 100 by nemělo existovat');

        $usersResult = $connection->query(sprintf(
            'SELECT id_uzivatele, login_uzivatele, jmeno_uzivatele, email1_uzivatele FROM `%s`.uzivatele_hodnoty WHERE id_uzivatele != %d',
            self::$anonymniDatabaze,
            \Uzivatel::SYSTEM,
        ));
        $users = $usersResult->fetchAll(\PDO::FETCH_ASSOC);

        // should have the anonymized original user + admin user
        self::assertGreaterThanOrEqual(2, count($users), 'Měli by být alespoň 2 uživatelé (anonymizovaný + admin)');

        // check admin user exists
        $adminResult = $connection->query(sprintf(
            "SELECT id_uzivatele, login_uzivatele FROM `%s`.uzivatele_hodnoty WHERE login_uzivatele = '%s'",
            self::$anonymniDatabaze,
            AnonymizovanaDatabaze::ADMIN_LOGIN,
        ));
        $admin = $adminResult->fetch(\PDO::FETCH_ASSOC);
        self::assertNotNull($admin, 'Admin uživatel by měl existovat');

        // admin should have organizer role
        $roleResult = $connection->query(sprintf(
            'SELECT id_role FROM `%s`.uzivatele_role WHERE id_uzivatele = %d',
            self::$anonymniDatabaze,
            $admin['id_uzivatele'],
        ));
        $roles = array_column($roleResult->fetchAll(\PDO::FETCH_ASSOC), 'id_role');
        self::assertContains((string) Role::ORGANIZATOR, $roles, 'Admin by měl mít roli organizátora');
        self::assertContains((string) Role::CFO, $roles, 'Admin by měl mít roli správce financí');

        // emails in stranky should be anonymized
        $strankyResult = $connection->query(sprintf(
            "SELECT obsah FROM `%s`.stranky WHERE url_stranky = 'test'",
            self::$anonymniDatabaze,
        ));
        $stranka = $strankyResult->fetch(\PDO::FETCH_ASSOC);
        self::assertNotNull($stranka, 'Stránka by měla existovat');
        self::assertStringNotContainsString('jan.novak@example.com', $stranka['obsah'], 'Email by měl být anonymizován');
        self::assertStringContainsString('foo@example.com', $stranka['obsah'], 'Email by měl být nahrazen za foo@example.com');

        // medailonky should be anonymized
        $medailonkyResult = $connection->query(sprintf(
            'SELECT o_sobe, drd FROM `%s`.medailonky',
            self::$anonymniDatabaze,
        ));
        $medailonky = $medailonkyResult->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($medailonky as $medailonek) {
            self::assertSame('', $medailonek['o_sobe'], 'Medailonek o_sobe by měl být prázdný');
            self::assertSame('', $medailonek['drd'], 'Medailonek drd by měl být prázdný');
        }

        // user personal data should be anonymized
        $anonymUsersResult = $connection->query(sprintf(
            "SELECT jmeno_uzivatele, prijmeni_uzivatele, telefon_uzivatele, ulice_a_cp_uzivatele FROM `%s`.uzivatele_hodnoty WHERE login_uzivatele != '%s' AND id_uzivatele != %d",
            self::$anonymniDatabaze,
            AnonymizovanaDatabaze::ADMIN_LOGIN,
            \Uzivatel::SYSTEM,
        ));
        $anonymUsers = $anonymUsersResult->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($anonymUsers as $anonymUser) {
            self::assertSame('', $anonymUser['jmeno_uzivatele'], 'Jméno by mělo být prázdné');
            self::assertSame('', $anonymUser['prijmeni_uzivatele'], 'Příjmení by mělo být prázdné');
        }

        $zpusobyZobrazeniResult = mysqli_query($connection, sprintf(
            "SELECT zpusob_zobrazeni_na_webu FROM `%s`.uzivatele_hodnoty WHERE login_uzivatele != '%s' AND id_uzivatele != %d",
            self::$anonymniDatabaze,
            AnonymizovanaDatabaze::ADMIN_LOGIN,
            \Uzivatel::SYSTEM,
        ));
        $zpusobyZobrazeni = array_column(mysqli_fetch_all($zpusobyZobrazeniResult, MYSQLI_ASSOC), 'zpusob_zobrazeni_na_webu');
        foreach ($zpusobyZobrazeni as $zpusobZobrazeni) {
            self::assertSame(
                (string) ZpusobZobrazeniNaWebu::POUZE_PREZDIVKA->value,
                $zpusobZobrazeni,
                'Způsob zobrazení na webu by měl být po anonymizaci resetovaný na pouze přezdívku',
            );
        }

        // posazen in uzivatele_role should be anonymized to fixed timestamp (except admin added after anonymization)
        $roleTimesResult = $connection->query(sprintf(
            'SELECT DISTINCT posazen FROM `%s`.uzivatele_role WHERE id_uzivatele != %d',
            self::$anonymniDatabaze,
            $admin['id_uzivatele'],
        ));
        $posazenValues = array_column($roleTimesResult->fetchAll(\PDO::FETCH_ASSOC), 'posazen');
        foreach ($posazenValues as $posazen) {
            self::assertSame('1970-01-01 01:01:01', $posazen, 'Časy posazen by měly být anonymizovány');
        }
    }

    public function testNelzeAnonymizovatDoStejneDatabaze(): void
    {
        $this->expectException(\LogicException::class);

        $systemoveNastaveni = SystemoveNastaveni::zGlobals();
        new AnonymizovanaDatabaze(
            DB_NAME,
            DB_NAME,
            $systemoveNastaveni,
            new NastrojeDatabaze($systemoveNastaveni),
        );
    }
}
