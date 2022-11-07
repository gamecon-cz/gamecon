<?php
ini_set('display_errors', true); // zobrazovat chyby při lokálním vývoji (pokud by se stala chyba dřív, než zobrazování chyb převezme Tracy)

// uživatel s základním přístupem
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_NAME')) define('DB_NAME', 'gamecon');
if (!defined('DB_SERV')) define('DB_SERV', '127.0.0.1');
//if (!defined('DB_PORT')) define('DB_PORT', '3306');

// uživatel s přístupem k změnám struktury
if (!defined('DBM_USER')) define('DBM_USER', DB_USER);
if (!defined('DBM_PASS')) define('DBM_PASS', DB_PASS);
//if (!defined('DBM_PORT')) define('DBM_PORT', DB_PORT);

$baseUrl = (($_SERVER['HTTPS'] ?? 'off') === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '/gamecon');
if (!defined('URL_WEBU')) define('URL_WEBU', $baseUrl . '/web'); // absolutní url uživatelského webu
if (!defined('URL_ADMIN')) define('URL_ADMIN', $baseUrl . '/admin'); // absolutní url adminu
if (!defined('URL_CACHE')) define('URL_CACHE', $baseUrl . '/cache/public'); // url sdílených cachí

if (!defined('ANALYTICS')) define('ANALYTICS', false);
if (!defined('MIGRACE_HESLO')) define('MIGRACE_HESLO', '');
if (!defined('HTTPS_ONLY')) define('HTTPS_ONLY', false);
if (!defined('SECRET_CRYPTO_KEY')) define('SECRET_CRYPTO_KEY', 'def0000066cba9ae32fdda839a143276cc0646b3880920c93876ecc1bbaca96ee6ed251559516b1804f4742c2165e4c7eb3ed5c7a5abe857c6db8608e3b5fe97a8cdf15a');

if (!defined('GOOGLE_API_CREDENTIALS')) define('GOOGLE_API_CREDENTIALS', []);
if (!defined('POVOLEN_OPAKOVANY_IMPORT_AKTIVIT_ZE_STEJNEHO_SOUBORU')) define('POVOLEN_OPAKOVANY_IMPORT_AKTIVIT_ZE_STEJNEHO_SOUBORU', true);
if (!defined('IMPOR_AKTIVIT_JENOM_JAKO')) define('IMPOR_AKTIVIT_JENOM_JAKO', false);

// nepovinné konstanty
if (!defined('CRON_KEY')) define('CRON_KEY', '123');
if (!defined('UNIVERZALNI_HESLO')) define('UNIVERZALNI_HESLO', ''); // obejití zadávání hesla pro vývojové prostředí
if (!defined('FIO_TOKEN')) define('FIO_TOKEN', '123456'); // přístup k api fio banky pro načítání plateb
if (!defined('MAILY_DO_SOUBORU')) define('MAILY_DO_SOUBORU', __DIR__ . '/../cache/private/maily.log');
if (!defined('AUTOMATICKE_MIGRACE')) define('AUTOMATICKE_MIGRACE', true);
if (!defined('AUTOMATICKA_TVORBA_DB')) define('AUTOMATICKA_TVORBA_DB', true);
if (!defined('PROFILOVACI_LISTA')) define('PROFILOVACI_LISTA', true);
if (!defined('CACHE_SLOZKY_PRAVA')) define('CACHE_SLOZKY_PRAVA', 0777);
if (!defined('ZOBRAZIT_STACKTRACE_VYJIMKY')) define('ZOBRAZIT_STACKTRACE_VYJIMKY', true);

error_reporting(E_ALL);
