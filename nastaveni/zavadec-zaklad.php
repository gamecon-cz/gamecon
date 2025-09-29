<?php

/**
 * Soubor, který zpřístupní definice pro gamecon (třídy, konstanty).
 */

require_once __DIR__ . '/zavadec-autoloader.php';

// starý model s pomocí funkcí
require_once __DIR__ . '/../model/funkce/fw-general.php';
require_once __DIR__ . '/../model/funkce/fw-database.php';
require_once __DIR__ . '/../model/funkce/funkce.php';
require_once __DIR__ . '/../model/funkce/web-funkce.php';
require_once __DIR__ . '/../model/funkce/skryte-nastaveni-z-env-funkce.php';

// načtení konfiguračních konstant

error_reporting(E_ALL & ~E_NOTICE); // skrýt notice, aby se konstanty daly "přetížit" dřív vloženými

require __DIR__ . '/zavadec-nastaveni.php';

// výchozí hodnoty konstant
// (nezobrazovat chyby, pokud už konstanta byla nastavena dřív)
$puvodniErrorReporting = error_reporting();
error_reporting($puvodniErrorReporting ^ E_NOTICE);
require_once __DIR__ . '/nastaveni.php';
error_reporting($puvodniErrorReporting);

if (defined('URL_WEBU') && URL_WEBU) {
    $domain = parse_url(URL_WEBU, PHP_URL_HOST) ?: 'localhost';
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => $domain !== 'localhost'
            ? ".$domain"
            : $domain, // Chrome-based browsers consider .localhost cookie domain as invalid (as localhost can not have subdomains)
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'lax',
    ]);
    // rozdilne nazvy pro ruzne instance (ostra, beta...), aby si PHP session cookies nelezly do zeli
    session_name('PS0' . preg_replace('~[^a-z0-9]~i', '0', $domain));
}


// Set environment variables for Symfony to use the same database names as legacy
putenv('GAMECON_DB_NAME=' . DB_NAME);
putenv('GAMECON_DB_ANONYM_NAME=' . DB_ANONYM_NAME);
putenv('GAMECON_DB_HOST=' . DB_SERV);
putenv('GAMECON_DB_PORT=' . DB_PORT);
putenv('GAMECON_DB_USER=' . DB_USER);
putenv('GAMECON_DB_PASSWORD=' . DB_PASS);
putenv('DEFAULT_URI=' . URL_WEBU);
if (!getenv('APP_SECRET')) {
    putenv('APP_SECRET=' . APP_SECRET);
}
