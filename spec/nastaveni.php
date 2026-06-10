<?php

// Archive 2016 per-deployment config — committed to archive/2016.
//
// In the old app/+spec/ layout, spec/ is the per-deployment config dir
// (sibling of app/, normally gitignored and written by hand on each host).
// app/zavadec.php loads this file via require __DIR__.'/../spec/nastaveni.php'.
// On the archive branch we commit it with env-driven DB reads; the host's
// deploy-year-archive.sh passes DB_USER / DB_PASS / DB_NAME via docker run -e,
// derived from HMAC(year, /etc/year-archive-deployer/secret).

error_reporting(E_ALL);
date_default_timezone_set('Europe/Prague');

// DB connection — env-driven (deploy script passes these via docker run -e).
// 2016-era fw-database connects via mysqli_connect('p:'.DB_SERV, ...) with no
// explicit port, so DB_SERV alone (docker0 bridge IP, default 172.17.0.1)
// reaches the host MariaDB on 3306.
define('DB_USER', getenv('DB_USER') ?: 'gamecon_2016');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'gamecon_2016');
define('DB_SERV', getenv('DB_SERV') ?: '172.17.0.1');

// Schema-change user — archives don't run migrations at runtime; the 2016
// site left DBM_* empty, so keep that (the app only uses DBM_* in admin
// migration paths which archives never hit).
define('DBM_USER', getenv('DBM_USER') ?: '');
define('DBM_PASS', getenv('DBM_PASS') ?: '');
define('DBM_NAME', getenv('DBM_NAME') ?: '');
define('DBM_SERV', getenv('DBM_SERV') ?: '');

// Branch selector — archives run as the production ("ostra") branch.
define('VYVOJOVA', 1);
define('OSTRA', 2);
define('VETEV', OSTRA);

// Path constants (relative to this spec/ dir). app/zavadec.php uses SPEC
// immediately (Vyjimkovac, XTemplate cache), so they must be defined here.
define('SPECIFICKE', __DIR__);
define('WWW',   __DIR__ . '/../app/web');
define('ADMIN', __DIR__ . '/../app/admin');
define('SPEC',  __DIR__ . '/cache-priv');
define('CACHE', __DIR__ . '/cache');

// Archive 2016: single subdomain serves the whole year, with /admin and
// /cache/public as path aliases of the one Apache DocumentRoot. The original
// 2016 config used separate admin.2016 / cache.2016 sub-subdomains (no longer
// published in DNS) — flip them to path-based like the other archives.
define('URL_WEBU',  'https://2016.gamecon.cz');               // absolutní url uživatelského webu
define('URL_ADMIN', 'https://2016.gamecon.cz/admin');         // absolutní url adminu (path-based)
define('URL_CACHE', 'https://2016.gamecon.cz/cache/public');  // url sdílených cachí (path-based)

define('GOOGLE_ANALYTICS', false);
