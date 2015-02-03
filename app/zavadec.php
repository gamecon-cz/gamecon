<?php

/**
 * Soubor který připraví prostředí aplikace: autoloadery, konstanty atd
 */

// autoloader Gamecon webu (modelu)
spl_autoload_register(function($trida){
  $trida = strtolower(preg_replace('@[A-Z]@', '-$0', lcfirst($trida)));
  include(__DIR__.'/model/'.$trida.'.php');
});

// autoloader Composeru
require __DIR__.'/vendor/autoload.php';

// starý model s pomocí funkcí
require __DIR__.'/model/funkce/fw-general.php';
require __DIR__.'/model/funkce/fw-database.php';
require __DIR__.'/model/funkce/funkce.php';

// nastavení pomocí glob. konstant
require __DIR__.'/../spec/nastaveni.php';
require __DIR__.'/nastaveni.php';







