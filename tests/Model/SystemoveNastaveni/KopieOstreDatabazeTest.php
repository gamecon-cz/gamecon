<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\SystemoveNastaveni;

use App\Kernel;
use Gamecon\Cache\ProgramStaticFileGenerator;
use Gamecon\Cache\ProgramStaticFileType;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\Prostredi\Prostredi;
use Gamecon\SystemoveNastaveni\DatabazoveNastaveni;
use Gamecon\SystemoveNastaveni\KopieOstreDatabaze;
use Gamecon\SystemoveNastaveni\NastrojeDatabaze;
use Gamecon\SystemoveNastaveni\SqlMigrace;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Vyjimkovac\Vyjimkovac;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class KopieOstreDatabazeTest extends TestCase
{
    private static ?string $soucasnaDbName = null;
    private static ?string $ostraDbName = null;
    private ?\Throwable $setUpError = null;

    private ?SystemoveNastaveni $systemoveNastaveni = null;
    private ?string $izolovanyPrivateCacheDir = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$soucasnaDbName = uniqid(DB_TEST_PREFIX . 'soucasna_', true);
        self::$ostraDbName = uniqid(DB_TEST_PREFIX . 'ostra_', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->izolovanyPrivateCacheDir = sys_get_temp_dir() . '/gamecon-test-kopie-private-' . getmypid() . '-' . mt_rand();
        (new Filesystem())->mkdir($this->izolovanyPrivateCacheDir);

        try {
            $this->systemoveNastaveni = $this->vytvorSystemoveNastaveni();

            $docasneSpojeniSoucasna = $this->docasneSpojeniSoucasna($this->systemoveNastaveni, true);
            $testDumps = scandir(__DIR__ . '/../../Db/data', SCANDIR_SORT_DESCENDING);
            assert($testDumps !== false, 'Nepodařilo se načíst testovací SQL');
            $latestDump = __DIR__ . '/../../Db/data/' . reset($testDumps);

            (new \MySQLImport($docasneSpojeniSoucasna))
                ->load($latestDump);
            // potřebujeme co největší rozdíl SQL migrací abychom vyzkoušeli že nějaká co není na betě a pustí se to nerozbije (stalo se)
            (new SqlMigrace($this->systemoveNastaveni))->migruj(false);

            $docasneSpojeniOstra = $this->docasneSpojeniOstra($this->systemoveNastaveni, true);
            // naplníme "jakoby ostrou" staršími daty, abychom vyzkoušeli nejen zkopírování, ale i migrace
            (new \MySQLImport($docasneSpojeniOstra))
                ->load($latestDump);
        } catch (\Throwable $throwable) {
            $this->setUpError = $throwable;
        }
    }

    protected function tearDown(): void
    {
        if ($this->izolovanyPrivateCacheDir !== null) {
            (new Filesystem())->remove($this->izolovanyPrivateCacheDir);
        }

        parent::tearDown();
    }

    /**
     * Protože @see setUp errors jsou PHPUnitem zahozeny a jinak bychom detail neměli
     */
    public function testSetUpProbehl()
    {
        if ($this->setUpError) {
            throw $this->setUpError;
        }
        self::assertTrue(true, 'Set up databází úspěšný');
    }

    private function docasneSpojeniSoucasna(
        SystemoveNastaveni $systemoveNastaveni,
        bool $resetDatabaze,
    ): \mysqli {
        [
            'DBM_USER' => $dbmUser,
            'DBM_PASS' => $dbmPass,
            'DB_NAME'  => $dbName,
            'DB_SERV'  => $dbServer,
            'DB_PORT'  => $dbPort,
        ] = $this->systemoveNastaveni->prihlasovaciUdajeSoucasneDatabaze();

        return $this->docasneSpojeni(
            $dbServer,
            $dbmUser,
            $dbmPass,
            $dbPort,
            $dbName,
            $systemoveNastaveni->rocnik(),
            $resetDatabaze,
        );
    }

    private function docasneSpojeniOstra(
        SystemoveNastaveni $systemoveNastaveni,
        bool $resetDatabaze,
    ): \mysqli {
        [
            'DBM_USER' => $dbmUser,
            'DBM_PASS' => $dbmPass,
            'DB_NAME'  => $dbName,
            'DB_SERV'  => $dbServer,
            'DB_PORT'  => $dbPort,
        ] = $this->systemoveNastaveni->prihlasovaciUdajeOstreDatabaze();

        return $this->docasneSpojeni(
            $dbServer,
            $dbmUser,
            $dbmPass,
            $dbPort,
            $dbName,
            $systemoveNastaveni->rocnik(),
            $resetDatabaze,
        );
    }

    private function docasneSpojeni(
        string $dbServer,
        string $dbmUser,
        string $dbmPass,
        string|int|null $dbPort,
        string $dbName,
        int $rocnik,
        bool $resetDatabaze,
    ) {
        $spojeni = _dbConnect(
            $dbServer,
            $dbmUser,
            $dbmPass,
            $dbPort,
            null,
            false,
        );

        if ($resetDatabaze) {
            dbQuery(sprintf('DROP DATABASE IF EXISTS `%s`', $dbName), [], $spojeni);
            dbQuery(sprintf('CREATE DATABASE IF NOT EXISTS `%s` COLLATE "utf8_czech_ci"', $dbName), [], $spojeni);
        }
        dbQuery(sprintf('USE `%s`', $dbName), [], $spojeni);

        _nastavRocnikDoSpojeni($rocnik, $spojeni, true);

        return $spojeni;
    }

    public function testZkopirujOstrouDatabazi()
    {
        $systemoveNastaveni = $this->vytvorSystemoveNastaveni();

        $spojeniSoucasna = $this->docasneSpojeniSoucasna($systemoveNastaveni, false);
        $tablesBefore = dbQuery('SHOW TABLES', $spojeniSoucasna)->fetch_all();

        $nastrojeDatabaze = new NastrojeDatabaze($systemoveNastaveni);
        $kopieOstreDatabaze = new KopieOstreDatabaze($nastrojeDatabaze, $systemoveNastaveni, Vyjimkovac::vytvorZGlobals());
        $nastaveniOstre = $systemoveNastaveni->prihlasovaciUdajeOstreDatabaze();
        $kopieOstreDatabaze->zkopirujDatabazi($nastaveniOstre['DB_NAME']);

        $tablesAfter = dbQuery('SHOW TABLES', $spojeniSoucasna)->fetch_all();
        self::assertGreaterThan(count($tablesBefore), $tablesAfter, 'Nějaké tabulky měly přibýt migracemi');
    }

    /**
     * Regression: po importu DB musí být JSON program cache označená jako dirty,
     * jinak frontend dál servíruje stará data z aktivity.json. SqlMigrace::migruj()
     * sama invaliduje jen když přibyly migrace, takže když ostrá databáze už má
     * všechny migrace aplikované (běžný stav produkce), bez vlastní invalidace
     * v KopieOstreDatabaze by k označení cache nikdy nedošlo.
     *
     * Reportováno v https://trello.com/c/XkQrBvbK.
     */
    public function testZkopirujOstrouDatabaziOznaciJsonCacheJakoDirtyIKdyzMigraceNicNeprinesly()
    {
        $systemoveNastaveni = $this->vytvorSystemoveNastaveni();

        // Migrujeme i ostrou DB, aby následný migruj() po importu byl skutečně
        // no-op — to je situace, kterou tento regresní test pokrývá (produkce
        // má zpravidla všechny migrace už aplikované).
        (new SqlMigrace($this->ostraSystemoveNastaveni($systemoveNastaveni)))->migruj(false);

        // Vynulujeme dirty flagy zbytkové po setUp (migrace na soucasna).
        $generator = new ProgramStaticFileGenerator($systemoveNastaveni);
        foreach (ProgramStaticFileType::cases() as $typ) {
            $generator->deleteDirtyFlag($typ);
            self::assertFalse(
                $generator->hasDirtyFlag($typ),
                "Předpoklad testu: vynulované flagy před importem ({$typ->value})",
            );
        }

        $nastrojeDatabaze = new NastrojeDatabaze($systemoveNastaveni);
        $kopieOstreDatabaze = new KopieOstreDatabaze($nastrojeDatabaze, $systemoveNastaveni, Vyjimkovac::vytvorZGlobals());
        $nastaveniOstre = $systemoveNastaveni->prihlasovaciUdajeOstreDatabaze();
        $kopieOstreDatabaze->zkopirujDatabazi($nastaveniOstre['DB_NAME']);

        // Sanity: SqlMigrace::migruj() uvnitř zkopirujDatabazi nemělo co aplikovat,
        // takže jeho vnitřní invalidace neproběhla — flagy musí pocházet z vlastní
        // invalidace v KopieOstreDatabaze.
        self::assertFalse(
            (new SqlMigrace($systemoveNastaveni))->nejakeMigraceKeSpusteni(),
            'Sanity: po importu nesmí zbýt žádné migrace ke spuštění',
        );

        foreach (ProgramStaticFileType::cases() as $typ) {
            self::assertTrue(
                $generator->hasDirtyFlag($typ),
                "Po importu DB musí být dirty flag pro {$typ->value}",
            );
        }
    }

    /**
     * SystemoveNastaveni nasměrované na ostrou DB — potřebujeme abychom proti ní
     * mohli spustit SqlMigrace::migruj v rámci přípravy testu.
     */
    private function ostraSystemoveNastaveni(SystemoveNastaveni $produkcniNastaveni): SystemoveNastaveni
    {
        $ostra = $produkcniNastaveni->prihlasovaciUdajeOstreDatabaze();

        return new class($ostra['DB_NAME'], $this->izolovanyPrivateCacheDir) extends SystemoveNastaveni {
            public function __construct(
                private readonly string $ostraDbName,
                string $privateCacheDir,
            ) {
                parent::__construct(
                    rocnik: ROCNIK,
                    ted: new DateTimeImmutableStrict(),
                    prostredi: Prostredi::Beta,
                    databazoveNastaveni: DatabazoveNastaveni::vytvorZGlobals(),
                    rootAdresarProjektu: PROJECT_ROOT_DIR,
                    privateCacheDir: $privateCacheDir,
                    kernel: new Kernel('test', false),
                    publicCacheDir: CACHE,
                );
            }

            public function prihlasovaciUdajeSoucasneDatabaze(): array
            {
                return [
                    'DBM_USER' => DBM_USER,
                    'DBM_PASS' => DBM_PASS,
                    'DB_NAME'  => $this->ostraDbName,
                    'DB_SERV'  => DB_SERV,
                    'DB_PORT'  => null,
                ];
            }
        };
    }

    private function vytvorSystemoveNastaveni(): SystemoveNastaveni
    {
        return new class(self::$soucasnaDbName, self::$ostraDbName, $this->izolovanyPrivateCacheDir) extends SystemoveNastaveni {
            public function __construct(
                private readonly string $soucasnaDbName,
                private readonly string $ostraDbName,
                string $privateCacheDir,
            ) {
                parent::__construct(
                    rocnik: ROCNIK,
                    ted: new DateTimeImmutableStrict(),
                    prostredi: Prostredi::Beta,
                    databazoveNastaveni: DatabazoveNastaveni::vytvorZGlobals(),
                    rootAdresarProjektu: PROJECT_ROOT_DIR,
                    privateCacheDir: $privateCacheDir,
                    kernel: new Kernel('test', false),
                    publicCacheDir: CACHE,
                );
            }

            public function prihlasovaciUdajeSoucasneDatabaze(): array
            {
                return [
                    'DBM_USER' => DBM_USER,
                    'DBM_PASS' => DBM_PASS,
                    'DB_NAME'  => $this->soucasnaDbName,
                    'DB_SERV'  => DB_SERV,
                    'DB_PORT'  => null,
                ];
            }

            public function prihlasovaciUdajeOstreDatabaze(): array
            {
                return [
                    'DBM_USER' => DBM_USER,
                    'DBM_PASS' => DBM_PASS,
                    'DB_NAME'  => $this->ostraDbName,
                    'DB_SERV'  => DB_SERV,
                    'DB_PORT'  => null,
                ];
            }
        };
    }
}
