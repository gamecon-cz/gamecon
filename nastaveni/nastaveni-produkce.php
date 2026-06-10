<?php

// Archive 2017 production settings — committed to archive/2017.
//
// In the main-line gamecon repo this file is .gitignored and an operator
// writes it by hand at year-flip time, holding hardcoded DB credentials.
// On archive/YYYY branches we commit it with env-driven reads instead;
// the host's deploy-year-archive.sh sets DB_USER / DB_PASS / DB_NAME via
// `docker run -e`, derived from HMAC(year, /etc/year-archive-deployer/secret).
//
// 2017-era config is older than 2018: zavadec.php requires VETEV (OSTRA /
// VYVOJOVA) and the path constants (WWW/ADMIN/SPEC/CACHE) are defined per
// environment file (there is no nastaveni-vychozi.php), so produkce must
// define them all.

// Branch selector — archives run as the production ("ostra") branch.
define('VETEV', OSTRA);

// Path constants (relative to this file's dir). zavadec.php uses SPEC
// immediately after loading this file, so they must be defined here.
define('WWW',   __DIR__ . '/../web');
define('ADMIN', __DIR__ . '/../admin');
define('SPEC',  __DIR__ . '/../cache/private');
define('CACHE', __DIR__ . '/../cache/public');

// Archive 2017: single subdomain serves the whole year, with /admin and
// /cache/public as path aliases of the one Apache DocumentRoot.
define('URL_WEBU',  'https://2017.gamecon.cz');               // absolutní url uživatelského webu
define('URL_ADMIN', 'https://2017.gamecon.cz/admin');         // absolutní url adminu (path-based)
define('URL_CACHE', 'https://2017.gamecon.cz/cache/public');  // url sdílených cachí (path-based)

// DB connection — env-driven (deploy script passes these via docker run -e).
// 2017-era fw-database connects via mysqli_connect('p:'.DB_SERV, ...) with no
// explicit port, so DB_SERV alone (the docker0 bridge IP, default 172.17.0.1)
// reaches the host MariaDB on 3306.
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER')
    ?: 'gamecon_2017');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS')
    ?: '');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME')
    ?: 'gamecon_2017');
if (!defined('DB_SERV')) define('DB_SERV', getenv('DB_SERV')
    ?: '172.17.0.1');

// Schema-change user. Archives don't run migrations at runtime; default to
// the same as DB_USER. (2017-era code references DBM_* in some paths.)
if (!defined('DBM_USER')) define('DBM_USER', getenv('DBM_USER')
    ?: DB_USER);
if (!defined('DBM_PASS')) define('DBM_PASS', getenv('DBM_PASS')
    ?: DB_PASS);
if (!defined('DBM_NAME')) define('DBM_NAME', getenv('DBM_NAME')
    ?: DB_NAME);
if (!defined('DBM_SERV')) define('DBM_SERV', getenv('DBM_SERV')
    ?: DB_SERV);

define('GOOGLE_ANALYTICS', true);
define('HTTPS_ONLY', true);

// Production-only constants the legacy code references — empty/default so the
// code is happy without exposing live credentials. Archives don't accept
// payments, run cron, or call external services.
if (!defined('FIO_TOKEN')) define('FIO_TOKEN', '');
if (!defined('CRON_KEY')) define('CRON_KEY', '');
if (!defined('MIGRACE_HESLO')) define('MIGRACE_HESLO', '');
