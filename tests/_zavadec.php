<?php

namespace Gamecon\Tests;

use Gamecon\Tests\Db\DbTest;
use Gamecon\Tests\Db\DbWrapper;
use Godric\DbMigrations\DbMigrationsConfig;
use Godric\DbMigrations\DbMigrations;

define('DB_NAME', uniqid('gamecon_test_', true));
define('SPEC', sys_get_temp_dir());
define('UNIT_TESTS', true);

// konfigurace
// TODO dokud není konfigurace vyřešena jinak, než přes konstanty, musíme testovat jen jeden vydefinovaný stav, tj. "reg na aktivity i GC běží"
define('REG_GC_OD', '2000-01-01 00:00:00');
define('REG_GC_DO', '2038-01-01 00:00:00');
define('REG_AKTIVIT_OD', '2000-01-01 00:00:00');
define('REG_AKTIVIT_DO', '2038-01-01 00:00:00');

define('BONUS_ZA_1H_AKTIVITU', 1);
define('BONUS_ZA_2H_AKTIVITU', 2);
define('BONUS_ZA_STANDARDNI_3H_AZ_5H_AKTIVITU', 3);
define('BONUS_ZA_6H_AZ_7H_AKTIVITU', 6);
define('BONUS_ZA_8H_AZ_9H_AKTIVITU', 8);
define('BONUS_ZA_10H_AZ_11H_AKTIVITU', 10);
define('BONUS_ZA_12H_AZ_13H_AKTIVITU', 12);

define('MAILY_DO_SOUBORU', '/dev/null'); // TODO přidat speciální nastavení pro CI

require_once __DIR__ . '/../nastaveni/zavadec-zaklad.php';

// příprava databáze
dbConnect(false);
dbQuery(sprintf('DROP DATABASE IF EXISTS `%s`', DB_NAME));
dbQuery(sprintf('CREATE DATABASE IF NOT EXISTS `%s` COLLATE "utf8_czech_ci"', DB_NAME));
dbQuery(sprintf('USE `%s`', DB_NAME));

(new DbMigrations(new DbMigrationsConfig([
    'connection'          => dbConnect(), // předpokládá se, že spojení pro testy má administrativní práva
    'migrationsDirectory' => __DIR__ . '/../migrace',
    'doBackups'           => false,
])))->run();

dbConnect(); // nutno inicalizovat spojení

DbTest::setConnection(new DbWrapper());

register_shutdown_function(static function () {
    dbQuery(sprintf('DROP DATABASE IF EXISTS `%s`', DB_NAME));
});
