<?php

namespace Gamecon\Tests;

use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Tests\Db\DbWrapper;
use Godric\DbMigrations\DbMigrationsConfig;
use Godric\DbMigrations\DbMigrations;

require_once __DIR__ . '/../nastaveni/verejne-nastaveni-tests.php';
require_once __DIR__ . '/../nastaveni/zavadec-zaklad.php';

$dbWrapper = new DbWrapper();
$dbWrapper->resetTestDb();

/**
 * pokud chceš vyřadit STRICT_TRANS_TABLES (potlačit "Field 'nazev_akce' doesn't have a default value"), použij @see \Gamecon\Tests\Db\DbTest::$disableStrictTransTables
 * Inspirace @see \Gamecon\Tests\Aktivity\AktivitaTagyTest::setUpBeforeClass
 */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
AbstractTestDb::setConnection($dbWrapper);

/** vynutíme reconnect, hlavně kvůli nastavení ROCNIK v databázi, @see \dbConnect */
dbClose();

register_shutdown_function(static function () {
    // nemůžeme použít předchozí $connection, protože to už je uzavřené
    $connection = dbConnectTemporary();

    // force stop any remaining processes running on test DB
    $fullProcessList  = dbFetchAll('SHOW FULL PROCESSLIST');
    $testDbProcesses  = array_filter($fullProcessList, static fn(array $process) => $process['db'] === DB_NAME);
    $testDbProcessIds = array_map(static fn(array $process) => $process['Id'], $testDbProcesses);
    foreach ($testDbProcessIds as $testDbProcessId) {
        try {
            dbQuery(<<<SQL
            KILL {$testDbProcessId}
            SQL,
                $connection,
            );
        } catch (\DbConnectionKilledException|\MysqlServerHasGoneAwayException $dbExcetion) {
        }
    }

    dbQuery(sprintf('DROP DATABASE IF EXISTS `%s`', DB_NAME), null, $connection);
    $dbTestPrefix            = DB_TEST_PREFIX;
    $oldTestDatabasesWrapped = dbFetchAll("SHOW DATABASES LIKE '{$dbTestPrefix}%'", [], $connection);
    foreach ($oldTestDatabasesWrapped as $oldTestDatabaseWrapped) {
        dbQuery(sprintf('DROP DATABASE IF EXISTS `%s`', reset($oldTestDatabaseWrapped)), null, $connection);
    }

    $rootCacheDir = SPEC;
    if (preg_match('~/[0-9]+$~', $rootCacheDir)) {
        $rootCacheDir = dirname($rootCacheDir);
    }
    shell_exec('rm -rf ' . escapeshellarg($rootCacheDir));
});
