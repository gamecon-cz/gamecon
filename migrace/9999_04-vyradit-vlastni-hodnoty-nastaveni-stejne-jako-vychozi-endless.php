<?php
/** @var \Godric\DbMigrations\Migration $this */

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\SystemoveNastaveni\SqlStruktura\SystemoveNastaveniSqlStruktura as NastaveniSql;

require_once __DIR__ . '/pomocne/rocnik_z_promenne_mysql.php';

$rocnik             = rocnik_z_promenne_mysql();
$systemoveNastaveni = SystemoveNastaveni::vytvorZGlobals($rocnik);

$vychoziHodnotySqlParts = [];
foreach ($systemoveNastaveni->dejVychoziHodnoty() as $klic => $vychoziHodnota) {
    $vychoziHodnotySqlParts[] = sprintf(
        '(%s = %s AND %s = %s)',
        NastaveniSql::KLIC,
        dbQv($klic),
        NastaveniSql::HODNOTA,
        dbQv($vychoziHodnota),
    );
}
$vychoziHodnotySql = '(' . implode(' OR ', $vychoziHodnotySqlParts) . ')';

$jakykoliRocnik = SystemoveNastaveni::JAKYKOLI_ROCNIK;
$this->q(<<<SQL
UPDATE systemove_nastaveni
    SET vlastni = 0
WHERE vlastni = 1
    AND rocnik_nastaveni IN ($rocnik, $jakykoliRocnik)
    AND $vychoziHodnotySql
SQL,
);
