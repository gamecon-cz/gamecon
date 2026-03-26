<?php

declare(strict_types=1);

namespace Gamecon\Tests;

use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Tests\Db\DbWrapper;

require_once __DIR__ . '/../nastaveni/verejne-nastaveni-tests.php';
require_once __DIR__ . '/../nastaveni/zavadec-zaklad.php';

$dbWrapper = new DbWrapper();
$dbWrapper->resetTestDb();

/*
 * pokud chceš vyřadit STRICT_TRANS_TABLES (potlačit "Field 'nazev_akce' doesn't have a default value"), použij @see \Gamecon\Tests\Db\DbTest::$disableStrictTransTables
 * Inspirace @see \Gamecon\Tests\Aktivity\AktivitaTagyTest::setUpBeforeClass
 */
// PDO error mode is set in _dbConnect() via \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
AbstractTestDb::setConnection($dbWrapper);

/* vynutíme reconnect, hlavně kvůli nastavení ROCNIK v databázi, @see \dbConnect */
dbClose();

register_shutdown_function(static function () {
    // nemůžeme použít předchozí $connection, protože to už je uzavřené
    $connection = dbConnectTemporary();

    // force stop any remaining processes running on test DB
    $fullProcessList = dbFetchAll('SHOW FULL PROCESSLIST');
    $testDbProcesses = array_filter($fullProcessList, static fn (array $process) => $process['db'] === DB_NAME);
    $testDbProcessIds = array_map(static fn (array $process) => $process['Id'], $testDbProcesses);
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
    dbQuery(sprintf('DROP DATABASE IF EXISTS `%s`', DB_ANONYM_NAME), null, $connection);

    // Zbytky po bězích, které nedoběhly. Poznají se podle PID v názvu: když ten proces už
    // neběží, databáze nikomu nepatří. Dokud běží, je to cizí rozjetý běh — smazat mu ji
    // pod rukama znamená rozstřílet ho zevnitř (`MySQL server has gone away` a desítky
    // nesouvisejících chyb, které vypadají jako flaky testy).
    $dbTestPrefix = DB_TEST_PREFIX;
    $oldTestDatabasesWrapped = dbFetchAll("SHOW DATABASES LIKE '{$dbTestPrefix}%'", [], $connection);
    foreach ($oldTestDatabasesWrapped as $oldTestDatabaseWrapped) {
        $dbName = reset($oldTestDatabaseWrapped);
        if (! Db\TestDbPid::jeOpustena($dbName)) {
            continue;
        }
        dbQuery(sprintf('DROP DATABASE IF EXISTS `%s`', $dbName), null, $connection);
    }

    // Cache stejně jako databáze: svůj adresář a k tomu zbytky po mrtvých procesech.
    // Mazat rovnou rodiče (tak to bylo dřív) vezme cache i běhu, který zrovna běží —
    // a nechat po sobě jen svůj znamená, že adresáře Apache workerů, které žádný
    // shutdown handler nemají, se nesmažou nikdy.
    shell_exec('rm -rf ' . escapeshellarg(SPEC));
    foreach ((array) glob(dirname(SPEC) . '/*', GLOB_ONLYDIR) as $cizíCacheDir) {
        if (Db\TestDbPid::jeOpustenyAdresar($cizíCacheDir)) {
            shell_exec('rm -rf ' . escapeshellarg($cizíCacheDir));
        }
    }
});
