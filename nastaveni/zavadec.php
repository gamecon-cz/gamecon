<?php

/**
 * Zavaděč pro web - soubor který připraví prostředí aplikace: autoloadery,
 * konstanty, logování atd.
 */

require __DIR__ . '/zavadec-zaklad.php';

// nastavení cache složky pro třídy, které ji potřebují
pripravCache(SPEC . '/xtpl');
XTemplate::cache(SPEC . '/xtpl');

// automatické migrace
if(AUTOMATICKE_MIGRACE) {
  (new Godric\DbMigrations\DbMigrations([
    'connection'          =>  dbConnect(), // musí mít admin práva
    'migrationsDirectory' =>  __DIR__ . '/../migrace',
    'doBackups'           =>  false,
    'checkInitialMigrationChanges' => false,
    'webGui'              =>  true,
  ]))->run();
}

// zapnutí logování výjimek
$typZobrazeni = ZOBRAZIT_STACKTRACE_VYJIMKY ? Vyjimkovac::TRACY : Vyjimkovac::PICARD;
$vyjimkovac = new Vyjimkovac(SPEC . '/chyby.sqlite');
$vyjimkovac->zobrazeni($typZobrazeni);
$vyjimkovac->aktivuj();
