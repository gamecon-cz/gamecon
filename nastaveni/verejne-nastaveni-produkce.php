<?php
require_once __DIR__ . '/nastaveni-produkce.php';

// URL_WEBU / URL_ADMIN / URL_CACHE honour env-var overrides so a single
// Docker image can serve different prod/beta/preview deployments by
// .env file (see docs/docker-migration-plan.md §2.7). Default values
// preserve the legacy FTP-deploy behavior unchanged.
define('URL_WEBU', getenv('URL_WEBU') ?: 'https://gamecon.cz');         // absolutní url uživatelského webu
define('URL_ADMIN', getenv('URL_ADMIN') ?: 'https://admin.gamecon.cz'); // absolutní url adminu
define('URL_CACHE', getenv('URL_CACHE') ?: 'https://cache.gamecon.cz'); // url sdílených cachí

define('ANALYTICS', true);
define('HTTPS_ONLY', true);

define('REACT_V_PROHLIZECI', false);
define('AUTOMATICKE_SESTAVENI', false);
define('BABEL_BINARKA', null);

/** aktuální ročník -- při změně roku viz Překlápění ročníku @link PREKLOPENI_ROCNIKU_NAVOD.md */
define('ROCNIK', 2026);

error_reporting(E_ALL); // reportuje se vše, o zobrazení se stará výjimkovač
