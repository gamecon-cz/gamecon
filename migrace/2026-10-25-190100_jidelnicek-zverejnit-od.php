<?php

declare(strict_types=1);

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\SystemoveNastaveni\SystemoveNastaveniKlice;

/** @var Godric\DbMigrations\Migration $this */

require_once __DIR__ . '/pomocne/rocnik_z_promenne_mysql.php';

// Jídelní lístek dostáváme 14 dní před GameConem; od té chvíle si účastníci k objednaným
// porcím vybírají konkrétní jídla. `vlastni = 0`: platí výchozí hodnota dopočítaná z ročníku,
// hodnota v řádku je jen ta letošní pro toky, které konstanty nedefinují.
$vychoziDatum = SystemoveNastaveni::zGlobals(rocnik_z_promenne_mysql())
    ->dejVychoziHodnotu(SystemoveNastaveniKlice::JIDELNICEK_ZVEREJNIT_OD);
$vychoziDatumSql = dbQv($vychoziDatum);

$this->q(<<<SQL
INSERT IGNORE INTO systemove_nastaveni (klic, hodnota, vlastni, datovy_typ, nazev, popis, skupina, poradi,
                                        pouze_pro_cteni, rocnik_nastaveni)
VALUES ('JIDELNICEK_ZVEREJNIT_OD', {$vychoziDatumSql}, 0, 'date', 'Zveřejnění jídelníčku',
        'Od kdy je na webu vidět jídelníček a účastníci si k objednaným obědům a večeřím vybírají konkrétní jídla. Vybírat jde do ukončení prodeje jídla.',
        'Časy', 27, 0, -1)
SQL,
);
