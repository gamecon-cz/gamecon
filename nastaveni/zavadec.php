<?php

/**
 * Zavaděč pro web - soubor který připraví prostředí aplikace: autoloadery,
 * konstanty, logování atd.
 */

require __DIR__ . '/zavadec-zaklad.php';
require __DIR__ . '/../model/funkce/web-funkce.php';

// nastavení cache složky pro třídy, které ji potřebují
pripravCache(SPEC . '/xtpl');
XTemplate::cache(SPEC . '/xtpl');

// automatické migrace databáze
if(AUTOMATICKE_MIGRACE) {
    require __DIR__ . '/db-migrace.php';
}

// zapnutí logování výjimek
$typZobrazeni = ZOBRAZIT_STACKTRACE_VYJIMKY ? Vyjimkovac::TRACY : Vyjimkovac::PICARD;
$vyjimkovac = new Vyjimkovac(SPEC . '/chyby.sqlite');
$vyjimkovac->zobrazeni($typZobrazeni);
$vyjimkovac->aktivuj();
