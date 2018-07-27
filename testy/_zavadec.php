<?php

define('DB_NAME', 'gamecon_test'); // TODO přetížit údaje pro připojení nějak inteligentněji

// konfigurace
// TODO dokud není konfigurace vyřešena jinak, než přes konstanty, musíme testovat jen jeden vydefinovaný stav, tj. "reg na aktivity i GC běží"
define('REG_GC_OD', '2000-01-01 00:00:00');
define('REG_GC_DO', '2099-01-01 00:00:00');
define('REG_AKTIVIT_OD', '2000-01-01 00:00:00');
define('REG_AKTIVIT_DO', '2099-01-01 00:00:00');

require_once __DIR__ . '/../nastaveni/zavadec-zaklad.php';

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

// TODO vložení židlí a práv - nějak automatizovat
dbInsertUpdate('r_zidle_soupis', ['id_zidle' => Z_PRIHLASEN]);
dbInsertUpdate('r_prava_soupis', ['id_prava' => ID_PRAVO_PRIHLASEN]);
dbInsertUpdate('r_prava_zidle', [
  'id_prava' => ID_PRAVO_PRIHLASEN,
  'id_zidle' => Z_PRIHLASEN,
]);
dbInsertUpdate('akce_prihlaseni_stavy', ['id_stavu_prihlaseni' => 0]); // FCK

dbConnect(); // nutno inicalizovat spojení

Godric\DbTest\DbTest::setConnection(new GcDbWrapper);
