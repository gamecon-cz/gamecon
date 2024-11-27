<?php
require_once __DIR__ . '/nastaveni-produkce.php';

define('URL_WEBU', 'https://gamecon.cz');        // absolutní url uživatelského webu
define('URL_ADMIN', 'https://admin.gamecon.cz'); // absolutní url adminu
define('URL_CACHE', 'https://cache.gamecon.cz'); // url sdílených cachí

define('ANALYTICS', true);
define('HTTPS_ONLY', true);

define('REACT_V_PROHLIZECI', false);
define('AUTOMATICKE_SESTAVENI', false);
define('BABEL_BINARKA', null);

error_reporting(E_ALL); // reportuje se vše, o zobrazení se stará výjimkovač
