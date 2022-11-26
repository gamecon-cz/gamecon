<?php

use Gamecon\XTemplate\XTemplate;

ini_set('display_errors', false); // nezobrazovat chyby (lze přetížít, třeba v lokálních nastaveních)
// TODO nefunguje, opravit jak najede
//require_once __DIR__ . '/initial-fatal-error-handler.php'; // pro ten kritický kousek, než naběhne Tracy (Vyjimkovac)

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
    ? \Gamecon\Vyjimkovac\Vyjimkovac::TRACY
    : \Gamecon\Vyjimkovac\Vyjimkovac::PICARD;
$vyjimkovac   = new \Gamecon\Vyjimkovac\Vyjimkovac(SPEC . '/chyby.sqlite');
$vyjimkovac->zobrazeni($typZobrazeni);
$vyjimkovac->aktivuj();
define('SHUTDOWN_FUNCTION_REGISTERED', true);

// automatické migrace databáze
if (AUTOMATICKE_MIGRACE) {
    require __DIR__ . '/db-migrace.php';
}
