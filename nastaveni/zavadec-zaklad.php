<?php

/**
 * Soubor, který zpřístupní definice pro gamecon (třídy, konstanty).
 */

// autoloader Gamecon webu (modelu)
spl_autoload_register(function ($trida) {
    $trida = strtolower(preg_replace('@[A-Z]@', '-$0', lcfirst($trida)));
    $classFile = __DIR__ . '/../model/' . $trida . '.php';
    if (file_exists($classFile)) {
        include_once $classFile;
    }
});

// autoloader Composeru
require __DIR__ . '/../vendor/autoload.php';

// starý model s pomocí funkcí
require __DIR__ . '/../model/funkce/fw-general.php';
require __DIR__ . '/../model/funkce/fw-database.php';
require __DIR__ . '/../model/funkce/funkce.php';

// načtení konfiguračních konstant

error_reporting(E_ALL & ~E_NOTICE); // skrýt notice, aby se konstanty daly "přetížit" dřív vloženými

if(PHP_SAPI == 'cli' || $_SERVER['REMOTE_ADDR'] == '127.0.0.1' || $_SERVER['REMOTE_ADDR'] == '::1') {
  @include __DIR__ . '/nastaveni-local.php'; // nepovinné lokální nastavení
  require __DIR__ . '/nastaveni-local-default.php'; // výchozí lokální nastavení
} elseif(substr($_SERVER['SERVER_NAME'], -15) == 'beta.gamecon.cz') {
  require __DIR__ . '/nastaveni-beta.php';
} elseif(substr($_SERVER['SERVER_NAME'], -19) == 'redesign.gamecon.cz') {
  require __DIR__ . '/nastaveni-redesign.php';
} elseif($_SERVER['SERVER_NAME'] == 'admin.gamecon.cz' || $_SERVER['SERVER_NAME'] == 'gamecon.cz') {
  require __DIR__ . '/nastaveni-produkce.php';
} else {
  die('nelze načíst nastavení verze');
}

require __DIR__ . '/nastaveni.php';

// výchozí hodnoty konstant
// (nezobrazovat chyby, pokud už konstanta byla nastavena dřív)
$puvodniErrorReporting = error_reporting();
error_reporting($puvodniErrorReporting ^ E_NOTICE);
require __DIR__ . '/nastaveni-vychozi.php';
error_reporting($puvodniErrorReporting);
