<?php
require_once __DIR__ . '/nastaveni-produkce.php';

// Archive 2023: single subdomain serves the whole year, with /admin and
// /cache/public as path aliases of the one Apache DocumentRoot. The
// admin.2023 / cache.2023 sub-subdomains are no longer published in DNS.
// See docs/year-archive-phase0-recon.md in the ansible repo for the
// host-coupled-code audit that justifies this.
define('URL_WEBU', 'https://2023.gamecon.cz');               // absolutní url uživatelského webu
define('URL_ADMIN', 'https://2023.gamecon.cz/admin');        // absolutní url adminu (path-based)
define('URL_CACHE', 'https://2023.gamecon.cz/cache/public'); // url sdílených cachí (path-based, mirrors verejne-nastaveni-preview.php)

define('ANALYTICS', true);
define('HTTPS_ONLY', true);

define('REACT_V_PROHLIZECI', false);
define('AUTOMATICKE_SESTAVENI', false);
define('BABEL_BINARKA', null);

error_reporting(E_ALL); // reportuje se vše, o zobrazení se stará výjimkovač
