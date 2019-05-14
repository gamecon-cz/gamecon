<?php
$constants = [
    // uživatel s základním přístupem
    'DB_USER' => 'root',
    'DB_PASS' => '',
    'DB_NAME' => 'gamecon',
    'DB_SERV' => 'localhost',

    // uživatel s přístupem k změnám struktury
    'DBM_USER' => 'root',
    'DBM_PASS' => '',
    'DBM_NAME' => 'gamecon',
    'DBM_SERV' => 'localhost',

    'URL_WEBU' => '/gamecon/web', // absolutní url uživatelského webu
    'URL_ADMIN' => '/gamecon/admin', // absolutní url adminu
    'URL_CACHE' => '/gamecon/cache/public', // url sdílených cachí

    'ANALYTICS' => false,
    'MIGRACE_HESLO' => '',
    'HTTPS_ONLY' => false,
    'SECRET_CRYPTO_KEY' => 'def0000066cba9ae32fdda839a143276cc0646b3880920c93876ecc1bbaca96ee6ed251559516b1804f4742c2165e4c7eb3ed5c7a5abe857c6db8608e3b5fe97a8cdf15a',

    // nepovinné konstanty
    'CRON_KEY' => '123',
    'UNIVERZALNI_HESLO' => '', // obejití zadávání hesla pro vývojové prostředí
    'FIO_TOKEN' => '123456', // přístup k api fio banky pro načítání plateb
    'FTP_ZALOHA_DB' => 'ftp://user:password@server/directory', // FTP pro zálohy databáze
    'MAILY_DO_SOUBORU' => __DIR__ . '/../cache/private/maily.log',
    'AUTOMATICKE_MIGRACE' => true,
    'PROFILOVACI_LISTA' => true,
    'CACHE_SLOZKY_PRAVA' => 0777,
    'ZOBRAZIT_STACKTRACE_VYJIMKY' => true,
];
foreach ($constants as $constantName => $constantValue) {
    if (!defined($constantName)) {
        define($constantName, $constantValue);
    }
}
unset($constants, $constantName, $constantValue);

error_reporting(E_ALL);
