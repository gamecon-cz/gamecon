<?php

/**
 * Soubor, který zpřístupní definice pro gamecon (třídy, konstanty).
 */

// autoloader Gamecon webu (modelu)
spl_autoload_register(function($trida) {
  $trida = strtolower(preg_replace('@[A-Z]@', '-$0', lcfirst($trida)));
  @include __DIR__ . '/../model/' . $trida . '.php'; // chyby potlačeny - třída může být načtena i později pomocí composeru
});

// autoloader Composeru
require __DIR__ . '/../vendor/autoload.php';

// starý model s pomocí funkcí
require __DIR__ . '/../model/funkce/fw-general.php';
require __DIR__ . '/../model/funkce/fw-database.php';
require __DIR__ . '/../model/funkce/funkce.php';

// načtení konfiguračních konstant

// TODO refaktorovat:
// konstanty pro výběr prostředí / větve. Odstranit a převést na konstanty pro konkrétní funkcionalitu.
define('VYVOJOVA', 1);
define('OSTRA', 2);

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

// TODO refaktorovat:
// konstantu VETEV odstranit a dodělat specifické konstanty, příp. přidat nastaveni-default.php
if(!defined('VETEV')) throw new Exception('Konstanta VETEV není nastavena, nastavte na OSTRA nebo VYVOJOVA v lokálním souboru s nastavením');

require __DIR__ . '/nastaveni.php';
