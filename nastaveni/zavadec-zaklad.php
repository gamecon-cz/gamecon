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

$host = $_SERVER['SERVER_NAME'] ?? null;
if (PHP_SAPI == 'cli' || in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']) || ($_ENV['ENV'] ?? '') === 'local') {
    define('ENVIRONMENT', 'local');
  if (file_exists(__DIR__ . '/nastaveni-local.php')) {
    include __DIR__ . '/nastaveni-local.php'; // nepovinné lokální nastavení
  }
  require __DIR__ . '/nastaveni-local-default.php'; // výchozí lokální nastavení
} elseif (substr($_SERVER['SERVER_NAME'], -15) == 'beta.gamecon.cz') {
    define('ENVIRONMENT', 'beta');
  require __DIR__ . '/nastaveni-beta.php';
} elseif (str_ends_with($host, 'blackarrow.gamecon.cz')) {
  // TODO vymazat konstantu ENVIRONMENT a nahradit konstantou pro konkrétní
  // feature, tj. automatickou tvorbu databáze, pokud neexistuje.
  require __DIR__ . '/nastaveni-blackarrow.php';
} elseif ($_SERVER['SERVER_NAME'] == 'admin.gamecon.cz' || $_SERVER['SERVER_NAME'] == 'gamecon.cz') {
    define('ENVIRONMENT', 'produkce');
  require __DIR__ . '/nastaveni-produkce.php';
} else {
  echo 'Nepodařilo se detekovat prostředí, nelze načíst nastavení verze';
  exit(1);
}

require __DIR__ . '/nastaveni.php';

// výchozí hodnoty konstant
// (nezobrazovat chyby, pokud už konstanta byla nastavena dřív)
$puvodniErrorReporting = error_reporting();
error_reporting($puvodniErrorReporting ^ E_NOTICE);
require __DIR__ . '/nastaveni-vychozi.php';
error_reporting($puvodniErrorReporting);
