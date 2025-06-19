<?php

// Co se dá zjistit bez jiných konstant, autoloaderu a připojení k DB

if (!defined('ADRESAR_WEBU_S_OBRAZKY')) define('ADRESAR_WEBU_S_OBRAZKY', __DIR__ . '/../web');

if (!defined('PROJECT_ROOT_DIR')) define('PROJECT_ROOT_DIR', __DIR__ . '/..');
if (!defined('WWW')) define('WWW', __DIR__ . '/../web');
if (!defined('ADMIN')) define('ADMIN', __DIR__ . '/../admin');
if (!defined('SPEC')) define('SPEC', __DIR__ . '/../cache/private');
if (!defined('LOGY')) define('LOGY', __DIR__ . '/../logy');
if (!defined('CACHE')) define('CACHE', __DIR__ . '/../cache/public');
if (!defined('SQL_MIGRACE_DIR')) define('SQL_MIGRACE_DIR', __DIR__ . '/../migrace');
if (!defined('ZALOHA_DB_SLOZKA')) define('ZALOHA_DB_SLOZKA', __DIR__ . '/../backup/db'); // cesta pro zálohy databáze
if (!defined('ADMIN_STAMPS')) define('ADMIN_STAMPS', rtrim(ADMIN, '/') . '/stamps');
if (!defined('NAZEV_SPOLECNOSTI_GAMECON')) define('NAZEV_SPOLECNOSTI_GAMECON', 'GameCon z.s.');
if (!defined('ZAMERENI_FIRMY')) define('ZAMERENI_FIRMY', 'Největší festival nepočítačových her');

if (!defined('AUTOMATICKE_MIGRACE')) define('AUTOMATICKE_MIGRACE', false);
if (!defined('ZOBRAZIT_STACKTRACE_VYJIMKY')) define('ZOBRAZIT_STACKTRACE_VYJIMKY', false);
if (!defined('PROFILOVACI_LISTA')) define('PROFILOVACI_LISTA', false);
if (!defined('CACHE_SLOZKY_PRAVA')) define('CACHE_SLOZKY_PRAVA', 0770);

if (!defined('PRIJEMCI_CHYB')) define('PRIJEMCI_CHYB', ['it@gamecon.cz']);
