<?php

require_once __DIR__ . '/nastaveni-jakublounek.php';

define('DBM_NAME', DB_NAME);
define('DBM_SERV', DB_SERV);

define('URL_WEBU', 'http://jakublounek.gamecon.cz'); // absolutní url uživatelského webu
define('URL_ADMIN', 'http://admin.jakublounek.gamecon.cz'); // absolutní url adminu
define('URL_CACHE', 'http://cache.jakublounek.gamecon.cz'); // url sdílených cachí

define('ANALYTICS', false);
define('HTTPS_ONLY', false);

error_reporting(E_ALL);

// ruční spuštění registrace na betě
define('REG_GC_OD', '2000-01-01 00:00:00');
define('REG_AKTIVIT_OD', '2000-01-01 00:00:00');

@define('ROK', 2022);

@define('TESTING', true);

@define('ADRESAR_WEBU_S_OBRAZKY', __DIR__ . '/../../ostra/web');
