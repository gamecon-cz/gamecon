<?php

define('DB_NAME', 'gamecon_test'); // TODO přetížit údaje pro připojení nějak inteligentněji

// konfigurace
// TODO dokud není konfigurace vyřešena jinak, než přes konstanty, musíme testovat jen jeden vydefinovaný stav, tj. "reg na aktivity i GC běží"
define('REG_GC_OD', '2000-01-01 00:00:00');
define('REG_GC_DO', '2099-01-01 00:00:00');
define('REG_AKTIVIT_OD', '2000-01-01 00:00:00');
define('REG_AKTIVIT_DO', '2099-01-01 00:00:00');

require_once __DIR__ . '/../nastaveni/zavadec-zaklad.php';

// příprava databáze
dbQuery('CREATE DATABASE IF NOT EXISTS gamecon_test COLLATE "utf8_czech_ci"');
dbQuery('USE gamecon_test');
require '../db-migrations/vendor/autoload.php'; // TODO publikovat a normálně použít přes composer
(new Godric\DbMigrations\DbMigrations([
  'connection'          =>  dbConnect(), // předpokládá se, že spojení pro testy má administrativní práva
  'migrationsDirectory' =>  'migrace',
  'doBackups'           =>  false,
  // TODO povolit zálohy do samostatné složky a zprovoznit rollbacky při změně poslední migrace
]))->run();

// třída zajišťující volání do testovací DB pro testovací framework
class GcDbWrapper extends Godric\DbTest\DbWrapper {

  function begin() { dbBegin(); }
  function escape($value) { return dbQv($value); }
  function query($sql) { return dbQuery($sql); }
  function rollback() { dbRollback(); }

}

// alias třídy pro globální namespace
class GcDbTest extends Godric\DbTest\DbTest {

  /**
   * @return Uzivatel vrátí nového testovacího uživatele přihlášeného na GC
   */
  static function prihlasenyUzivatel() {
    $cislo = rand(1000, 9999);
    dbInsert('uzivatele_hodnoty', [
      'login_uzivatele'  => 'test_' . $cislo,
      'email1_uzivatele' => 'godric.cz+gc_test_' . $cislo . '@gmail.com',
    ]);
    $uid = dbInsertId();
    dbInsert('r_uzivatele_zidle', [
      'id_uzivatele'  =>  $uid,
      'id_zidle'      =>  Z_PRIHLASEN,
    ]);
    return Uzivatel::zId($uid);
  }

}

dbConnect(); // nutno inicalizovat spojení

Godric\DbTest\DbTest::setConnection(new GcDbWrapper);
