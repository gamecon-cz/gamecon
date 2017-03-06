<?php

error_reporting(E_ALL);

// uživatel s základním přístupem
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'gamecon');
define('DB_SERV', 'localhost');

// uživatel s přístupem k změnám struktury
define('DBM_USER', 'root');
define('DBM_PASS', '');
define('DBM_NAME', 'gamecon');
define('DBM_SERV', 'localhost');

define('VETEV', VYVOJOVA);

define('WWW',       __DIR__ . '/../web');
define('ADMIN',     __DIR__ . '/../admin');
define('SPEC',      __DIR__ . '/../cache/private');
define('CACHE',     __DIR__ . '/../cache/public');
define('URL_WEBU',  '/gamecon/web'); // absolutní url uživatelského webu
define('URL_ADMIN', '/gamecon/admin'); // absolutní url adminu
define('URL_CACHE', '/gamecon/cache/public'); // url sdílených cachí

define('GOOGLE_ANALYTICS', false);
define('CRON_KEY', '123');
define('UNIVERZALNI_HESLO', ''); // obejití zadávání hesla pro vývojové prostředí
define('MIGRACE_HESLO', '');
