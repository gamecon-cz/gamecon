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

        // PID musí zůstat na konci jména, jinak ho úklid v `_zavadec.php` nepozná
        // a cizí běh tuhle databázi smaže, i když ji tenhle test právě používá.
        self::$anonymniDatabaze = DB_NAME . '_anonym_' . getmypid();

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
            nechce_ubytovani = 1,
            poznamka = '',
            pomoc_typ = '',
            pomoc_vice = '',
            op = '',
            zpusob_zobrazeni_na_webu = 2,
            infopult_poznamka = ''
        ");

        dbQuery("INSERT INTO medailonky SET id_uzivatele = 100, o_sobe = 'Osobní info', drd = 'DRD info'");

        dbQuery("INSERT INTO stranky SET url_stranky = 'test', obsah = 'Kontakt: jan.novak@example.com', poradi = 1");

        self::vlozRadekDoKazdePrilisCitliveTabulky();
    }

    /**
     * Without a row in the source an emptied table would look the same as a table nobody empties.
     */
    private static function vlozRadekDoKazdePrilisCitliveTabulky(): void
    {
        $radkyPodleTabulky = [
            '_vars' => <<<'SQL'
                INSERT INTO _vars SET name = 'test', value = 'x'
                SQL,
            'platby' => <<<'SQL'
                INSERT INTO platby SET id_uzivatele = 100, castka = 1, rok = 2026, provedeno = NOW(), provedl = 100
                SQL,
            'akce_import' => <<<'SQL'
                INSERT INTO akce_import SET id_uzivatele = 100, google_sheet_id = 'sheet'
                SQL,
            'uzivatele_url' => <<<'SQL'
                INSERT INTO uzivatele_url SET id_uzivatele = 100, url = 'jan-novak'
                SQL,
            'obnova_hesla_log' => <<<'SQL'
                INSERT INTO obnova_hesla_log SET ip = '192.0.2.1', id_uzivatele = 100, odeslano = 1
                SQL,
            'google_api_user_tokens' => <<<'SQL'
                INSERT INTO google_api_user_tokens SET user_id = 100, google_client_id = 'client', tokens = 'tokens'
                SQL,
            'google_drive_dirs' => <<<'SQL'
                INSERT INTO google_drive_dirs SET user_id = 100, dir_id = 'dir', original_name = 'dir'
                SQL,
            'uzivatele_slucovani_log' => <<<'SQL'
                INSERT INTO uzivatele_slucovani_log
                SET id_smazaneho_uzivatele = 100, id_noveho_uzivatele = 101,
                    zustatek_smazaneho_puvodne = 0, zustatek_noveho_puvodne = 0, zustatek_noveho_aktualne = 0,
                    email_smazaneho = 'jan.novak@example.com', email_noveho_puvodne = 'honza@example.com', email_noveho_aktualne = 'honza@example.com'
                SQL,
            'newsletter_prihlaseni' => <<<'SQL'
                INSERT INTO newsletter_prihlaseni SET email = 'jan.novak@example.com'
                SQL,
            'newsletter_prihlaseni_log' => <<<'SQL'
                INSERT INTO newsletter_prihlaseni_log SET email = 'jan.novak@example.com', stav = 'prihlasen'
                SQL,
            'uzivatele_hodnoty_log' => <<<'SQL'
                INSERT INTO uzivatele_hodnoty_log SET id_uzivatele = 100, sloupec = 'jmeno_uzivatele', stara_hodnota = 'Jan', nova_hodnota = 'Honza'
                SQL,
            'slevove_kody' => <<<'SQL'
                INSERT INTO slevove_kody SET kod = 'SLEVA', createdBy = 100, createdAt = NOW()
                SQL,
            'akce_tym' => <<<'SQL'
                INSERT INTO akce_tym SET id = 1, kod = 1, nazev = 'Tým Nováků', id_kapitan = 100
                SQL,
            'akce_tym_akce' => <<<'SQL'
                INSERT INTO akce_tym_akce SET id_tymu = 1, id_akce = 1
                SQL,
            'akce_tym_prihlaseni' => <<<'SQL'
                INSERT INTO akce_tym_prihlaseni SET id_tymu = 1, id_uzivatele = 100
                SQL,
            'log_udalosti' => <<<'SQL'
                INSERT INTO log_udalosti SET id_logujiciho = 100, zprava = 'Gratulujeme týmu Nováků', metadata = '{"ucastnik":100}', rok = 2026
                SQL,
            'slevy' => <<<'SQL'
                INSERT INTO slevy SET id_uzivatele = 100, castka = 1, rok = 2026, poznamka = 'Honza dostal slevu'
                SQL,
            'upominka_dluznika_log' => <<<'SQL'
                INSERT INTO upominka_dluznika_log SET id_uzivatele = 100, typ_upominky = 'prvni', dluh = 1, rocnik = 2026, odeslal = 100
                SQL,
            'upominka_vlastni_zneni' => <<<'SQL'
                INSERT INTO upominka_vlastni_zneni SET rocnik = 2026, predmet = 'Předmět', text = 'Text', zmenil = 100
                SQL,
        ];

        self::assertEqualsCanonicalizing(
            AnonymizovanaDatabaze::PRILIS_CITLIVE_TABULKY,
            array_keys($radkyPodleTabulky),
            'Pro každou citlivou tabulku musí test vložit řádek',
        );

        // the activity and the second user that these rows point at do not exist
        dbQuery('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach ($radkyPodleTabulky as $vlozeniRadku) {
                dbQuery($vlozeniRadku);
            }
        } finally {
            dbQuery('SET FOREIGN_KEY_CHECKS = 1');
        }
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
        mysqli_query($connection, sprintf('DROP DATABASE IF EXISTS `%s`', self::$anonymniDatabaze));
        mysqli_query($connection, sprintf('CREATE DATABASE `%s` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci', self::$anonymniDatabaze));
        mysqli_query($connection, sprintf('USE `%s`', self::$anonymniDatabaze));

        $anonymizovanaDatabaze->obnov($connection);

        // anonymous DB should have the tables
        $tablesResult = mysqli_query($connection, sprintf("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '%s'", self::$anonymniDatabaze));
        $tables = array_column(mysqli_fetch_all($tablesResult, MYSQLI_ASSOC), 'TABLE_NAME');
        self::assertContains('uzivatele_hodnoty', $tables, 'Anonymní databáze by měla obsahovat tabulku uzivatele_hodnoty');
        self::assertContains('stranky', $tables, 'Anonymní databáze by měla obsahovat tabulku stranky');
        self::assertContains('medailonky', $tables, 'Anonymní databáze by měla obsahovat tabulku medailonky');

        // sensitive tables should be empty
        foreach (AnonymizovanaDatabaze::PRILIS_CITLIVE_TABULKY as $sensitivniTabulka) {
            $countResult = mysqli_query($connection, sprintf('SELECT COUNT(*) as cnt FROM `%s`.`%s`', self::$anonymniDatabaze, $sensitivniTabulka));
            $count = mysqli_fetch_assoc($countResult)['cnt'];
            self::assertSame('0', $count, "Tabulka {$sensitivniTabulka} by měla být prázdná");
        }

        // user ID should be anonymized (different from original 100)
        $originalIdResult = mysqli_query($connection, sprintf(
            'SELECT COUNT(*) as cnt FROM `%s`.uzivatele_hodnoty WHERE id_uzivatele = 100',
            self::$anonymniDatabaze,
        ));
        self::assertSame('0', mysqli_fetch_assoc($originalIdResult)['cnt'], 'Původní ID 100 by nemělo existovat');

        $usersResult = mysqli_query($connection, sprintf(
            'SELECT id_uzivatele, login_uzivatele, jmeno_uzivatele, email1_uzivatele FROM `%s`.uzivatele_hodnoty WHERE id_uzivatele != %d',
            self::$anonymniDatabaze,
            \Uzivatel::SYSTEM,
        ));
        $users = mysqli_fetch_all($usersResult, MYSQLI_ASSOC);

        // should have the anonymized original user + admin user
        self::assertGreaterThanOrEqual(2, count($users), 'Měli by být alespoň 2 uživatelé (anonymizovaný + admin)');

        // check admin user exists
        $adminResult = mysqli_query($connection, sprintf(
            "SELECT id_uzivatele, login_uzivatele FROM `%s`.uzivatele_hodnoty WHERE login_uzivatele = '%s'",
            self::$anonymniDatabaze,
            AnonymizovanaDatabaze::ADMIN_LOGIN,
        ));
        $admin = mysqli_fetch_assoc($adminResult);
        self::assertNotNull($admin, 'Admin uživatel by měl existovat');

        // admin should have organizer role
        $roleResult = mysqli_query($connection, sprintf(
            'SELECT id_role FROM `%s`.uzivatele_role WHERE id_uzivatele = %d',
            self::$anonymniDatabaze,
            $admin['id_uzivatele'],
        ));
        $roles = array_column(mysqli_fetch_all($roleResult, MYSQLI_ASSOC), 'id_role');
        self::assertContains((string) Role::ORGANIZATOR, $roles, 'Admin by měl mít roli organizátora');
        self::assertContains((string) Role::CFO, $roles, 'Admin by měl mít roli správce financí');

        // emails in stranky should be anonymized
        $strankyResult = mysqli_query($connection, sprintf(
            "SELECT obsah FROM `%s`.stranky WHERE url_stranky = 'test'",
            self::$anonymniDatabaze,
        ));
        $stranka = mysqli_fetch_assoc($strankyResult);
        self::assertNotNull($stranka, 'Stránka by měla existovat');
        self::assertStringNotContainsString('jan.novak@example.com', $stranka['obsah'], 'Email by měl být anonymizován');
        self::assertStringContainsString('foo@example.com', $stranka['obsah'], 'Email by měl být nahrazen za foo@example.com');

        // medailonky should be anonymized
        $medailonkyResult = mysqli_query($connection, sprintf(
            'SELECT o_sobe, drd FROM `%s`.medailonky',
            self::$anonymniDatabaze,
        ));
        $medailonky = mysqli_fetch_all($medailonkyResult, MYSQLI_ASSOC);
        foreach ($medailonky as $medailonek) {
            self::assertSame('', $medailonek['o_sobe'], 'Medailonek o_sobe by měl být prázdný');
            self::assertSame('', $medailonek['drd'], 'Medailonek drd by měl být prázdný');
        }

        // user personal data should be anonymized
        $anonymUsersResult = mysqli_query($connection, sprintf(
            "SELECT jmeno_uzivatele, prijmeni_uzivatele, telefon_uzivatele, ulice_a_cp_uzivatele, nechce_ubytovani FROM `%s`.uzivatele_hodnoty WHERE login_uzivatele != '%s' AND id_uzivatele != %d",
            self::$anonymniDatabaze,
            AnonymizovanaDatabaze::ADMIN_LOGIN,
            \Uzivatel::SYSTEM,
        ));
        $anonymUsers = mysqli_fetch_all($anonymUsersResult, MYSQLI_ASSOC);
        foreach ($anonymUsers as $anonymUser) {
            self::assertSame('', $anonymUser['jmeno_uzivatele'], 'Jméno by mělo být prázdné');
            self::assertSame('', $anonymUser['prijmeni_uzivatele'], 'Příjmení by mělo být prázdné');
            self::assertSame('0', $anonymUser['nechce_ubytovani'], 'Volba nechce ubytování by měla být vynulovaná');
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
        $roleTimesResult = mysqli_query($connection, sprintf(
            'SELECT DISTINCT posazen FROM `%s`.uzivatele_role WHERE id_uzivatele != %d',
            self::$anonymniDatabaze,
            $admin['id_uzivatele'],
        ));
        $posazenValues = array_column(mysqli_fetch_all($roleTimesResult, MYSQLI_ASSOC), 'posazen');
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
