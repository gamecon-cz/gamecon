<?php

namespace Gamecon\Tests;

use Gamecon\Tests\Db\DbTest;
use Gamecon\Tests\Db\DbWrapper;
use Godric\DbMigrations\DbMigrationsConfig;
use Godric\DbMigrations\DbMigrations;

define('DB_NAME', uniqid('gamecon_test_', true));
define('SPEC', sys_get_temp_dir());

// konfigurace
// TODO dokud není konfigurace vyřešena jinak, než přes konstanty, musíme testovat jen jeden vydefinovaný stav, tj. "reg na aktivity i GC běží"
define('REG_GC_OD', '2000-01-01 00:00:00');
define('REG_GC_DO', '2038-01-01 00:00:00');
define('REG_AKTIVIT_OD', '2000-01-01 00:00:00');
define('REG_AKTIVIT_DO', '2038-01-01 00:00:00');

define('MAILY_DO_SOUBORU', '/dev/null'); // TODO přidat speciální nastavení pro CI

require_once __DIR__ . '/../nastaveni/zavadec-zaklad.php';

// příprava databáze
dbConnect(false);
dbQuery(sprintf('DROP DATABASE IF EXISTS `%s`', DB_NAME));
dbQuery(sprintf('CREATE DATABASE IF NOT EXISTS `%s` COLLATE "utf8_czech_ci"', DB_NAME));
dbQuery(sprintf('USE `%s`', DB_NAME));

(new DbMigrations(new DbMigrationsConfig([
    'connection' => dbConnect(), // předpokládá se, že spojení pro testy má administrativní práva
    'migrationsDirectory' => __DIR__ . '/../migrace',
    'doBackups' => false,
])))->run(); // migrations v1

(new DbMigrations(new DbMigrationsConfig([
    'connection' => dbConnect(), // předpokládá se, že spojení pro testy má administrativní práva
    'migrationsDirectory' => __DIR__ . '/../migrace',
    'doBackups' => false,
])))->run();// migrations v2

dbConnect(); // nutno inicalizovat spojení

DbTest::setConnection(new DbWrapper());

register_shutdown_function(static function () {
    dbQuery(sprintf('DROP DATABASE IF EXISTS `%s`', DB_NAME));
});
