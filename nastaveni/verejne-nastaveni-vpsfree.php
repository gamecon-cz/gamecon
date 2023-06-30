<?php
require_once __DIR__ . '/nastaveni-vpsfree.php';

define('URL_WEBU', 'https://vpsfree.gamecon.cz'); // absolutní url uživatelského webu
define('URL_ADMIN', 'https://admin.vpsfree.gamecon.cz'); // absolutní url adminu
define('URL_CACHE', 'https://cache.vpsfree.gamecon.cz'); // url sdílených cachí

define('ANALYTICS', true);
define('HTTPS_ONLY', true);

define('REACT_V_PROHLIZECI', false);
define('AUTOMATICKE_SESTAVENI', false);
define('BABEL_BINARKA', null);

error_reporting(E_ALL); // reportuje se vše, o zobrazení se stará výjimkovač
