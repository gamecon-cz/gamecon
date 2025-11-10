<?php
ini_set('display_errors', true); // zobrazovat chyby při lokálním vývoji (pokud by se stala chyba dřív, než zobrazování chyb převezme Tracy)

// uživatel s základním přístupem
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_NAME', 'gamecon');
define('DB_SERV', 'sql.gamecon');

// uživatel s přístupem k změnám struktury
define('DBM_USER', 'root');
define('DBM_PASS', 'root');

if (!defined('DB_ANONYM_SERV')) define('DB_ANONYM_SERV', DB_SERV);
if (!defined('DB_ANONYM_USER')) define('DB_ANONYM_USER', DBM_USER);
if (!defined('DB_ANONYM_PASS')) define('DB_ANONYM_PASS', DBM_PASS);
//if (!defined('DB_ANONYM_PORT')) define('DB_ANONYM_PORT', DB_PORT);
if (!defined('DB_ANONYM_NAME')) define('DB_ANONYM_NAME', 'anonymni_databaze');

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
@define('URL_WEBU', $baseUrl . '/web'); // absolutní url uživatelského webu
@define('URL_ADMIN', $baseUrl . '/admin'); // absolutní url adminu
@define('URL_CACHE', $baseUrl . '/cache/public'); // url sdílených cachí

define('ANALYTICS', false);
define('MIGRACE_HESLO', '');
define('HTTPS_ONLY', false);
define('SECRET_CRYPTO_KEY', 'def0000066cba9ae32fdda839a143276cc0646b3880920c93876ecc1bbaca96ee6ed251559516b1804f4742c2165e4c7eb3ed5c7a5abe857c6db8608e3b5fe97a8cdf15a');

@define('GOOGLE_API_CREDENTIALS', []);
@define('POVOLEN_OPAKOVANY_IMPORT_AKTIVIT_ZE_STEJNEHO_SOUBORU', true);
@define('IMPOR_AKTIVIT_JENOM_JAKO', false);

// nepovinné konstanty
define('CRON_KEY', '123');
define('UNIVERZALNI_HESLO', 'heslo123'); // obejití zadávání hesla pro vývojové prostředí
define('FIO_TOKEN', '123456'); // přístup k api fio banky pro načítání plateb
define('MAILY_DO_SOUBORU', __DIR__ . '/../cache/private/maily.log');
define('AUTOMATICKE_MIGRACE', true);
define('PROFILOVACI_LISTA', true);
define('CACHE_SLOZKY_PRAVA', 0777);
define('ZOBRAZIT_STACKTRACE_VYJIMKY', true);

if (!defined('MAILER_DSN')) define('MAILER_DSN', 'mailhog.gamecon:8025');

error_reporting(E_ALL);
