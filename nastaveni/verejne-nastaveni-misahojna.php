<?php

require_once __DIR__ . '/nastaveni-misahojna.php';

define('URL_WEBU', 'http://misahojna.gamecon.cz'); // absolutní url uživatelského webu
define('URL_ADMIN', 'http://admin.misahojna.gamecon.cz'); // absolutní url adminu
define('URL_CACHE', 'http://cache.misahojna.gamecon.cz'); // url sdílených cachí

define('ANALYTICS', false);
define('HTTPS_ONLY', false);

error_reporting(E_ALL);

// ruční spuštění registrace na betě
define('REG_GC_OD', '2000-01-01 00:00:00');
define('ZACATEK_PRVNI_VLNY', '2000-01-01 00:00:00');

@define('ROCNIK', 2022);

@define('TESTING', true);

@define('ADRESAR_WEBU_S_OBRAZKY', __DIR__ . '/../../ostra/web');
