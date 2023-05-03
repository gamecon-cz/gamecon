<?php

namespace Gamecon\Tests\Model\SystemoveNastaveni;

use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\SystemoveNastaveni\DatabazoveNastaveni;
use Gamecon\SystemoveNastaveni\KopieOstreDatabaze;
use Gamecon\SystemoveNastaveni\NastrojeDatabaze;
use Gamecon\SystemoveNastaveni\SqlMigrace;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use PHPUnit\Framework\TestCase;

class KopieOstreDatabazeTest extends TestCase
{
    private static ?string $soucasnaDbName = null;
    private static ?string $ostraDbName    = null;
    private ?\Throwable    $setUpError     = null;

    private ?SystemoveNastaveni $systemoveNastaveni = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$soucasnaDbName = uniqid(DB_TEST_PREFIX . 'soucasna_', true);
        self::$ostraDbName    = uniqid(DB_TEST_PREFIX . 'ostra_', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->systemoveNastaveni = $this->vytvorSystemoveNastaveni();

            $docasneSpojeniSoucasna = $this->docasneSpojeniSoucasna($this->systemoveNastaveni, true);
            (new \MySQLImport($docasneSpojeniSoucasna))
                ->load(__DIR__ . '/../../Db/data/localhost-2023_01_27_11_18_45-dump.sql');
            // potřebujeme co největší rozdíl SQL migrací abychom vyzkoušeli že nějaká co není na betě a pustí se to nerozbije (stalo se)
            (new SqlMigrace($this->systemoveNastaveni))->migruj(false);

            $docasneSpojeniOstra = $this->docasneSpojeniOstra($this->systemoveNastaveni, true);
            // naplníme "jakoby ostrou" staršími daty, abychom vyzkoušeli nejen zkopírování, ale i migrace
            (new \MySQLImport($docasneSpojeniOstra))
                ->load(__DIR__ . '/../../Db/data/localhost-2023_01_27_11_18_45-dump.sql');
        } catch (\Throwable $throwable) {
            $this->setUpError = $throwable;
        }
    }

    /** Protože @see setUp errors jsou PHPUnitem zahozeny a jinak bychom detail neměli */
    public function testSetUpProbehl()
    {
        if ($this->setUpError) {
            throw $this->setUpError;
        }
        self::assertTrue(true, 'Set up databází úspěšný');
    }

    private function docasneSpojeniSoucasna(
        SystemoveNastaveni $systemoveNastaveni,
        bool               $resetDatabaze,
    ): \mysqli
    {
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
        bool               $resetDatabaze,
    ): \mysqli
    {
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
        string          $dbServer,
        string          $dbmUser,
        string          $dbmPass,
        string|int|null $dbPort,
        string          $dbName,
        int             $rocnik,
        bool            $resetDatabaze,
    )
    {
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
        $tablesBefore    = dbQuery('SHOW TABLES', $spojeniSoucasna)->fetch_all();

        $nastrojeDatabaze   = new NastrojeDatabaze($systemoveNastaveni);
        $kopieOstreDatabaze = new KopieOstreDatabaze($nastrojeDatabaze, $systemoveNastaveni);
        $kopieOstreDatabaze->zkopirujOstrouDatabazi();

        $tablesAfter = dbQuery('SHOW TABLES', $spojeniSoucasna)->fetch_all();
        self::assertGreaterThan(count($tablesBefore), $tablesAfter, 'Nějaké tabulky měly přibýt migracemi');
    }

    private function vytvorSystemoveNastaveni(): SystemoveNastaveni
    {
        return new class(self::$soucasnaDbName, self::$ostraDbName) extends SystemoveNastaveni {

            public function __construct(
                private readonly string $soucasnaDbName,
                private readonly string $ostraDbName,
            )
            {
                parent::__construct(
                    ROCNIK,
                    new DateTimeImmutableStrict(),
                    true,
                    false,
                    DatabazoveNastaveni::vytvorZGlobals(),
                    PROJECT_ROOT_DIR,
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
