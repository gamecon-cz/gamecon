<?php

use Gamecon\XTemplate\XTemplate;
use Gamecon\Vyjimkovac\Vyjimkovac;

ini_set('display_errors', false); // nezobrazovat chyby (lze přetížít, třeba v lokálních nastaveních)
require_once __DIR__ . '/initial-fatal-error-handler.php'; // pro ten kritický kousek, než naběhne Tracy (Vyjimkovac)

/**
 * Zavaděč pro web - soubor který připraví prostředí aplikace: autoloadery,
 * konstanty, logování atd.
 */

require_once __DIR__ . '/zavadec-zaklad.php';

// nastavení cache složky pro třídy, které ji potřebují
pripravCache(SPEC . '/xtpl');
XTemplate::cache(SPEC . '/xtpl');

// zapnutí logování výjimek
$typZobrazeni = ZOBRAZIT_STACKTRACE_VYJIMKY
    ? Vyjimkovac::TRACY
    : Vyjimkovac::PICARD;
$vyjimkovac   = Vyjimkovac::vytvorZGlobals();
$vyjimkovac->zobrazeni($typZobrazeni);
$vyjimkovac->aktivuj();

// automatické migrace databáze
if (AUTOMATICKE_MIGRACE && !is_ajax()) {
    require __DIR__ . '/db-migrace.php';
}
