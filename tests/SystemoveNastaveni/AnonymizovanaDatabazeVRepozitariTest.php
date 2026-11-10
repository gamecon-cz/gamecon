<?php

declare(strict_types=1);

namespace Gamecon\Tests\SystemoveNastaveni;

use Gamecon\SystemoveNastaveni\AnonymizovanaDatabaze;
use Gamecon\SystemoveNastaveni\SystemoveNastaveniKlice;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\AnonymizovanyUzivatel;
use Gamecon\Uzivatel\SqlStruktura\UzivateleHodnotySqlStruktura as UzivatelSql;
use Godric\DbMigrations\DbMigrations;
use Godric\DbMigrations\DbMigrationsConfig;

/**
 * The database that a fresh checkout starts from: `nastaveni/db-migrace.php` loads the committed dump into an
 * empty database and runs the migrations over it.
 */
class AnonymizovanaDatabazeVRepozitariTest extends AbstractTestDb
{
    private const string DUMP = __DIR__ . '/../../migrace/pomocne/gc_anonymizovana_databaze.sql';
    private const string JAK_OBNOVIT = 'Obnov dump příkazem bin/stahni-anonymizovanou-databazi.sh, viz docs/generated/anonymizovana-databaze.md';

    /**
     * Tables that may keep rows in the committed dump, each reviewed for the public repository. A table with rows
     * that is in neither this list nor PRILIS_CITLIVE_TABULKY fails the test, so a new table gets reviewed first.
     */
    private const array PREKONTROLOVANE_TABULKY_S_DATY = [
        'akce_instance',
        'akce_lokace',
        'akce_organizatori',
        'akce_prihlaseni',
        'akce_prihlaseni_log',
        'akce_prihlaseni_spec',
        'akce_prihlaseni_stavy',
        'akce_seznam',
        'akce_sjednocene_tagy',
        'akce_stav',
        'akce_stavy_log',
        'akce_typy',
        'akce_typy_hlavicky',
        // lecture archive: published titles, speakers as the web shows them and YouTube ids; the link of a speaker
        // to an account tells no more than akce_organizatori with the public program
        'archiv_prednasek',
        'archiv_prednasek_klicove_slovo',
        'archiv_prednasek_prednasejici',
        'archiv_prednasek_prednaska_klicove_slovo',
        'archiv_prednasek_prednaska_prednasejici',
        // which cards the job after GC decided about, and its error when one failed
        'archiv_prednasek_zpracovane_aktivity',
        'emailova_sablona',
        'hromadne_akce_log',
        'kategorie_sjednocenych_tagu',
        'lokace',
        'medailonky',
        'migrations',
        'novinky',
        'obchod_bunky',
        'obchod_mrizky',
        'prava_role',
        'r_prava_soupis',
        'reporty',
        'reporty_log_pouziti',
        'reporty_prava',
        'reporty_quick',
        'role_seznam',
        'role_texty_podle_uzivatele',
        'shop_nakupy',
        'shop_nakupy_zrusene',
        'shop_predmety',
        'sjednocene_tagy',
        'stranky',
        'systemove_nastaveni',
        'systemove_nastaveni_log',
        'turnaje',
        'ubytovani',
        'uzivatele_hodnoty',
        'uzivatele_role',
        'uzivatele_role_log',
        'uzivatele_role_podle_rocniku',
        'uzivatele_vzhled_adminu',
        'uzivatele_vzhled_adminu_pouziti',
    ];

    /**
     * Columns of uzivatele_hodnoty that AnonymizovanyUzivatel leaves alone, reviewed for the public repository:
     * the shifted id, the gender and three flags or dates without a name or contact.
     */
    private const array PREKONTROLOVANE_SLOUPCE_UZIVATELE = [
        'id_uzivatele',
        'pohlavi',
        'formular_cizince_od',
        'z_rychloregistrace',
        'potvrzeni_zakonneho_zastupce_soubor',
    ];

    private static ?\mysqli $spojeni = null;

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

        try {
            // like nastaveni/db-migrace.php: no database selected until it is created
            self::$spojeni = dbConnectTemporary(false);
            dbQuery(sprintf(<<<'SQL'
                DROP DATABASE IF EXISTS `%s`
                SQL, DB_NAME), null, self::$spojeni);
            dbQuery(sprintf(<<<'SQL'
                CREATE DATABASE `%s` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci
                SQL, DB_NAME), null, self::$spojeni);
            dbQuery(sprintf(<<<'SQL'
                USE `%s`
                SQL, DB_NAME), null, self::$spojeni);
            (new \MySQLImport(self::$spojeni))->load(self::DUMP);
        } catch (\Throwable $throwable) {
            // PHPUnit does not call tearDownAfterClass() after a failed setUpBeforeClass(), and the half-loaded
            // database would break every later test class
            static::tearDownAfterClass();
            throw $throwable;
        }
    }

    public static function tearDownAfterClass(): void
    {
        // a cut-off import still holds LOCK TABLES, which would block the DROP DATABASE of the rebuild forever
        self::$spojeni?->close();
        self::$spojeni = null;
        parent::tearDownAfterClass();
    }

    public function testDumpNeobsahujeOsobniUdaje(): void
    {
        foreach (AnonymizovanaDatabaze::PRILIS_CITLIVE_TABULKY as $tabulka) {
            self::assertFalse(
                $this->maRadky($tabulka),
                "Tabulka {$tabulka} má být v dumpu prázdná. " . self::JAK_OBNOVIT,
            );
        }

        $tabulky = dbFetchColumn(<<<'SQL'
            SELECT table_name
            FROM information_schema.tables
            WHERE table_schema = $0 AND table_type = 'BASE TABLE'
            SQL, [
            0 => DB_NAME,
        ], self::spojeni());
        $nepreskoumane = [];
        foreach (array_diff($tabulky, AnonymizovanaDatabaze::PRILIS_CITLIVE_TABULKY, self::PREKONTROLOVANE_TABULKY_S_DATY) as $tabulka) {
            if ($this->maRadky($tabulka)) {
                $nepreskoumane[] = $tabulka;
            }
        }
        self::assertSame(
            [],
            $nepreskoumane,
            'Tyhle tabulky mají v dumpu řádky, které nikdo nepřekontroloval pro veřejný repozitář. Vyprázdni je v '
            . 'AnonymizovanaDatabaze::PRILIS_CITLIVE_TABULKY, nebo je po kontrole přidej do PREKONTROLOVANE_TABULKY_S_DATY.',
        );
    }

    public function testUzivateleVDumpuJsouAnonymni(): void
    {
        $anonymniUdaje = AnonymizovanyUzivatel::vytvorAnonymniUdajeProId(0);

        $nepreskoumaneSloupce = array_diff(
            dbFetchColumn(<<<'SQL'
                SELECT column_name
                FROM information_schema.columns
                WHERE table_schema = $0 AND table_name = 'uzivatele_hodnoty'
                SQL, [
                0 => DB_NAME,
            ], self::spojeni()),
            array_keys($anonymniUdaje),
            self::PREKONTROLOVANE_SLOUPCE_UZIVATELE,
        );
        self::assertSame(
            [],
            array_values($nepreskoumaneSloupce),
            'Tyhle sloupce uzivatele_hodnoty anonymizace nepřepisuje a nikdo je nepřekontroloval pro veřejný repozitář. '
            . 'Přepiš je v AnonymizovanyUzivatel, nebo je po kontrole přidej do PREKONTROLOVANE_SLOUPCE_UZIVATELE.',
        );

        $podminky = [];
        foreach ($anonymniUdaje as $sloupec => $hodnota) {
            $podminky[] = match ($sloupec) {
                UzivatelSql::LOGIN_UZIVATELE  => "`{$sloupec}` REGEXP " . dbQv('^Login[0-9]+$'),
                UzivatelSql::EMAIL1_UZIVATELE => "`{$sloupec}` REGEXP " . dbQv('^email[0-9]+@gamecon\.cz$'),
                UzivatelSql::REGISTROVAN      => 'TRUE',
                default                       => "`{$sloupec}` <=> " . dbQv($hodnota),
            };
        }
        $podminkyAnonymity = implode("\n    AND ", $podminky);

        $existujeNeanonymizovany = (bool) dbOneCol(<<<SQL
            SELECT EXISTS(
                SELECT 1
                FROM uzivatele_hodnoty
                WHERE login_uzivatele <> \$0
                    AND NOT (
                        {$podminkyAnonymity}
                    )
            )
            SQL, [
            0 => AnonymizovanaDatabaze::ADMIN_LOGIN,
        ], self::spojeni());

        self::assertFalse($existujeNeanonymizovany, 'V dumpu je uživatel, který neprošel anonymizací. ' . self::JAK_OBNOVIT);
    }

    /**
     * The migration `9999_03-letosni-systemove-nastaveni-endless.php` copies the settings of the previous year. Under
     * UNIT_TESTS it invents the missing record of PRUMERNE_LONSKE_VSTUPNE, so only this check notices a stale dump.
     * Has to stay the last test, because the migrations change the loaded dump that the tests above look at.
     */
    public function testMigracePodDumpemProjdou(): void
    {
        foreach (SystemoveNastaveniKlice::jednorocniKlice() as $klic) {
            self::assertTrue(
                (bool) dbOneCol(<<<'SQL'
                    SELECT EXISTS(
                        SELECT 1
                        FROM systemove_nastaveni
                        WHERE klic = $0 AND rocnik_nastaveni IN ($1, $2)
                    )
                    SQL, [
                    0 => $klic,
                    1 => ROCNIK,
                    2 => ROCNIK - 1,
                ], self::spojeni()),
                "Dump nezná nastavení {$klic} za ročník " . ROCNIK . ' ani ' . (ROCNIK - 1) . ', migrace nemá z čeho kopírovat. ' . self::JAK_OBNOVIT,
            );
        }

        (new DbMigrations(new DbMigrationsConfig(
            connection: self::spojeni(),
            migrationsDirectory: SQL_MIGRACE_DIR,
            doBackups: false,
        )))->run();

        $this->addToAssertionCount(1);
    }

    private static function spojeni(): \mysqli
    {
        return self::$spojeni ?? throw new \LogicException('Spojení s databází z dumpu ještě nebylo otevřeno');
    }

    private function maRadky(string $tabulka): bool
    {
        return (bool) dbOneCol("SELECT EXISTS(SELECT 1 FROM `{$tabulka}`)", null, self::spojeni());
    }
}
