<?php

namespace Gamecon\Tests;

use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Tests\Db\DbWrapper;
use Godric\DbMigrations\DbMigrationsConfig;
use Godric\DbMigrations\DbMigrations;

require_once __DIR__ . '/../nastaveni/verejne-nastaveni-tests.php';
require_once __DIR__ . '/../nastaveni/zavadec-zaklad.php';

// příprava databáze
$connection = dbConnectTemporary(false);
dbQuery(sprintf('DROP DATABASE IF EXISTS `%s`', DB_NAME), [], $connection);
dbQuery(sprintf('CREATE DATABASE IF NOT EXISTS `%s` COLLATE "utf8_czech_ci"', DB_NAME), [], $connection);
dbQuery(sprintf('USE `%s`', DB_NAME), [], $connection);

// naimportujeme databázi s už proběhnutými staršími migracemi
(new \MySQLImport($connection))->load(__DIR__ . '/Db/data/localhost-2023_01_27_11_18_45-dump.sql');

(new DbMigrations(new DbMigrationsConfig([
    'connection'          => $connection, // předpokládá se, že spojení pro testy má administrativní práva
    'migrationsDirectory' => __DIR__ . '/../migrace',
    'doBackups'           => false,
])))->run();

/**
 * pokud chceš vyřadit STRICT_TRANS_TABLES (potlačit "Field 'nazev_akce' doesn't have a default value"), použij @see \Gamecon\Tests\Db\DbTest::$disableStrictTransTables
 * Inspirace @see \Gamecon\Tests\Aktivity\AktivitaTagyTest::setUpBeforeClass
 */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

AbstractTestDb::setConnection(new DbWrapper());

/** vynutíme reconnect, hlavně kvůli nastavení ROCNIK v databázi, @see \dbConnect */
dbClose();

register_shutdown_function(static function () {
    // nemůžeme použít předchozí $connection, protože to už je uzavřené
    $connection = dbConnectTemporary();
    dbQuery(sprintf('DROP DATABASE IF EXISTS `%s`', DB_NAME), null, $connection);
    $dbTestPrefix            = DB_TEST_PREFIX;
    $oldTestDatabasesWrapped = dbFetchAll("SHOW DATABASES LIKE '{$dbTestPrefix}%'", [], $connection);
    foreach ($oldTestDatabasesWrapped as $oldTestDatabaseWrapped) {
        dbQuery(sprintf('DROP DATABASE IF EXISTS `%s`', reset($oldTestDatabaseWrapped)), null, $connection);
    }
});
