<?php

// Archive 2018 production settings — committed to archive/2018.
//
// In the main-line gamecon repo this file is .gitignored and an operator
// writes it by hand at year-flip time, holding hardcoded DB credentials.
// On archive/YYYY branches we commit it with env-driven reads instead;
// the host's deploy-year-archive.sh sets DB_USER / DB_PASS / DB_NAME via
// `docker run -e`, derived from HMAC(year, /etc/year-archive-deployer/secret).

// Archive 2018: single subdomain serves the whole year, with /admin and
// /cache/public as path aliases of the one Apache DocumentRoot. The
// admin.2018 / cache.2018 sub-subdomains are no longer published in DNS.
define('URL_WEBU', 'https://2018.gamecon.cz');               // absolutní url uživatelského webu
define('URL_ADMIN', 'https://2018.gamecon.cz/admin');        // absolutní url adminu (path-based)
define('URL_CACHE', 'https://2018.gamecon.cz/cache/public'); // url sdílených cachí (path-based)

// DB connection — env-driven (deploy script passes these via docker run -e).
// 2018-era fw-database connects via mysqli_connect('p:'.DB_SERV, ...) with no
// explicit port, so DB_SERV alone (the docker0 bridge IP, default 172.17.0.1)
// reaches the host MariaDB on 3306.
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER')
    ?: 'gamecon_2018');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS')
    ?: '');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME')
    ?: 'gamecon_2018');
if (!defined('DB_SERV')) define('DB_SERV', getenv('DB_SERV')
    ?: '172.17.0.1');

// Schema-change user. Archives don't run migrations at runtime; default to
// the same as DB_USER. (2018-era code references DBM_* in some paths.)
if (!defined('DBM_USER')) define('DBM_USER', getenv('DBM_USER')
    ?: DB_USER);
if (!defined('DBM_PASS')) define('DBM_PASS', getenv('DBM_PASS')
    ?: DB_PASS);
if (!defined('DBM_NAME')) define('DBM_NAME', getenv('DBM_NAME')
    ?: DB_NAME);
if (!defined('DBM_SERV')) define('DBM_SERV', getenv('DBM_SERV')
    ?: DB_SERV);

define('ANALYTICS', true);
define('HTTPS_ONLY', true);

// Production-only constants the legacy code references — empty/default so the
// code is happy without exposing live credentials. Archives don't accept
// payments, run cron, or call external services.
if (!defined('FIO_TOKEN')) define('FIO_TOKEN', '');
if (!defined('CRON_KEY')) define('CRON_KEY', '');
if (!defined('MIGRACE_HESLO')) define('MIGRACE_HESLO', '');
if (!defined('FTP_ZALOHA_DB')) define('FTP_ZALOHA_DB', '');

// SECRET_CRYPTO_KEY — used by Defuse\Crypto helpers in some paths; reuse the
// standard dummy from nastaveni-local-default.php.
if (!defined('SECRET_CRYPTO_KEY')) define('SECRET_CRYPTO_KEY', getenv('SECRET_CRYPTO_KEY')
    ?: 'def0000066cba9ae32fdda839a143276cc0646b3880920c93876ecc1bbaca96ee6ed251559516b1804f4742c2165e4c7eb3ed5c7a5abe857c6db8608e3b5fe97a8cdf15a');
