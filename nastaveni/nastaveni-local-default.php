<?php
ini_set('display_errors', true); // zobrazovat chyby při lokálním vývoji (pokud by se stala chyba dřív, než zobrazování chyb převezme Tracy)

// uživatel s základním přístupem
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER')
    ?: 'root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS')
    ?: '');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME')
    ?: 'gamecon');
if (!defined('DB_SERV')) define('DB_SERV', getenv('DB_SERV')
    ?: '127.0.0.1');
if (!defined('DB_PORT')) define('DB_PORT', (int)(getenv('DB_PORT')
    ?: '3306'));

// uživatel s přístupem k změnám struktury
if (!defined('DBM_USER')) define('DBM_USER', getenv('DBM_USER')
    ?: DB_USER);
if (!defined('DBM_PASS')) define('DBM_PASS', getenv('DBM_PASS')
    ?: DB_PASS);

if (!defined('DB_ANONYM_SERV')) define('DB_ANONYM_SERV', getenv('DB_ANONYM_SERV')
    ?: DB_SERV);
if (!defined('DB_ANONYM_USER')) define('DB_ANONYM_USER', getenv('DB_ANONYM_USER')
    ?: DBM_USER);
if (!defined('DB_ANONYM_PASS')) define('DB_ANONYM_PASS', getenv('DB_ANONYM_PASS')
    ?: DBM_PASS);
//if (!defined('DB_ANONYM_PORT')) define('DB_ANONYM_PORT', DB_PORT);
if (!defined('DB_ANONYM_NAME')) define('DB_ANONYM_NAME', getenv('DB_ANONYM_NAME')
    ?: 'anonymni_databaze');

$baseUrl = (($_SERVER['HTTPS'] ?? 'off') === 'on'
        ? 'https'
        : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
if (!defined('URL_WEBU')) getenv('URL_WEBU')
    ?: define('URL_WEBU', $baseUrl . '/web'); // absolutní url uživatelského webu
if (!defined('URL_ADMIN')) getenv('URL_ADMIN')
    ?: define('URL_ADMIN', $baseUrl . '/admin'); // absolutní url adminu
if (!defined('URL_CACHE')) getenv('URL_CACHE')
    ?: define('URL_CACHE', $baseUrl . '/cache/public'); // url sdílených cachí

if (!defined('ANALYTICS')) define('ANALYTICS', false);
if (!defined('MIGRACE_HESLO')) define('MIGRACE_HESLO', '');
if (!defined('HTTPS_ONLY')) define('HTTPS_ONLY', false);
if (!defined('SECRET_CRYPTO_KEY')) define('SECRET_CRYPTO_KEY', 'def0000066cba9ae32fdda839a143276cc0646b3880920c93876ecc1bbaca96ee6ed251559516b1804f4742c2165e4c7eb3ed5c7a5abe857c6db8608e3b5fe97a8cdf15a');

if (!defined('GOOGLE_API_CREDENTIALS')) define('GOOGLE_API_CREDENTIALS', []);
if (!defined('POVOLEN_OPAKOVANY_IMPORT_AKTIVIT_ZE_STEJNEHO_SOUBORU')) define('POVOLEN_OPAKOVANY_IMPORT_AKTIVIT_ZE_STEJNEHO_SOUBORU', true);
if (!defined('IMPOR_AKTIVIT_JENOM_JAKO')) define('IMPOR_AKTIVIT_JENOM_JAKO', false);

// nepovinné konstanty
if (!defined('CRON_KEY')) define('CRON_KEY', '123');
if (!defined('UNIVERZALNI_HESLO')) define('UNIVERZALNI_HESLO', getenv('UNIVERZALNI_HESLO')
    ?: ''); // obejití zadávání hesla pro vývojové prostředí
if (!defined('FIO_TOKEN')) define('FIO_TOKEN', '123456'); // přístup k api fio banky pro načítání plateb
if (!defined('MAILY_DO_SOUBORU')) define('MAILY_DO_SOUBORU', __DIR__ . '/../cache/private/maily.log');
if (!defined('AUTOMATICKE_MIGRACE')) define('AUTOMATICKE_MIGRACE', true);
if (!defined('PROFILOVACI_LISTA')) define('PROFILOVACI_LISTA', true);
if (!defined('CACHE_SLOZKY_PRAVA')) define('CACHE_SLOZKY_PRAVA', 0777);
if (!defined('ZOBRAZIT_STACKTRACE_VYJIMKY')) define('ZOBRAZIT_STACKTRACE_VYJIMKY', true);

if (!defined('MAILER_DSN')) define('MAILER_DSN', getenv('MAILER_DSN')
    ?: '');
if (!defined('VAROVAT_O_ZASEKLE_SYNCHRONIZACI_PLATEB')) define('VAROVAT_O_ZASEKLE_SYNCHRONIZACI_PLATEB', false);
if (!defined('OPENID_SECURITY_KEY')) define('OPENID_SECURITY_KEY', 'def00000cf934770d479bb98dc7d2560858d53edeaa1739615cc18fb755fd4af7f1d2034a121751ee5eefdaf1a2c14ff94097678889c6b0c413390a214e21b247f77723e');

error_reporting(E_ALL);
