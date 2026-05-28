<?php
// Archive 2014 per-deployment config — committed to archive/2014.
//
// In the old www/+sdilene/+specificke/ framework (pre-MVC, .hhp includes),
// specificke/site-specific.php is the per-deployment config, loaded by
// sdilene/vse.hhp via require_once('../specificke/site-specific.php').
// fwDatabase.hhp reads the $DATABASE_* globals and connects with mysql_connect.
// Normally hand-written on each host with hardcoded creds; on the archive
// branch we read them from the environment instead (the host's
// deploy-year-archive.sh passes DB_USER / DB_PASS / DB_NAME / DB_SERV via
// docker run -e, derived from HMAC(year, /etc/year-archive-deployer/secret)).

error_reporting ( E_ALL & ~E_DEPRECATED );

// DB connection — env-driven. 2014 fw-database connects via
// mysql_connect($DATABASE_SERVER, ...) with no port, so DB_SERV alone (the
// docker0 bridge IP, default 172.17.0.1) reaches the host MariaDB on 3306.
$DATABASE_USER     = getenv('DB_USER') ?: 'gamecon_2014';
$DATABASE_PASSWORD = getenv('DB_PASS') ?: '';
$DATABASE_NAME     = getenv('DB_NAME') ?: 'gamecon_2014';
$DATABASE_SERVER   = getenv('DB_SERV') ?: '172.17.0.1';

define ( 'VYVOJOVA', 1 );
define ( 'OSTRA', 2 );
define ( 'VETEV', OSTRA );

define ( 'ADMIN_WWW_CESTA', '../www' );    // cesta z rootu admina do rootu uživatelské části
define ( 'SDILENE_WWW_CESTA', '../www' );  // cesta z sdílených tříd do rootu uživatelské části
define ( 'SDILENE_ADMIN_CESTA', '../admin' ); // cesta z sdílených tříd do rootu adminu

// Archive 2014: single subdomain serves the year. The original config used
// http://2014.gamecon.cz for both web and admin; keep that shape but https.
define ( 'URL_WEBU', 'https://2014.gamecon.cz' );  // absolutní url uživatelského webu
define ( 'URL_ADMIN', 'https://2014.gamecon.cz' ); // absolutní url adminu

date_default_timezone_set ( 'Europe/Prague' );
