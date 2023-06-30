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

$host                     = $_SERVER['SERVER_NAME'] ?? 'localhost';
$souborVerejnehoNastaveni = null;
if (!empty($_COOKIE['unit_tests'])) {
    include __DIR__ . '/verejne-nastaveni-tests.php';
}
if (PHP_SAPI === 'cli' || in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']) || ($_ENV['ENV'] ?? '') === 'local') {
    if (file_exists(__DIR__ . '/nastaveni-local.php')) {
        include __DIR__ . '/nastaveni-local.php'; // nepovinné lokální nastavení
    }
    require_once __DIR__ . '/nastaveni-local-default.php'; // výchozí lokální nastavení
} else if (str_ends_with($host, 'beta.gamecon.cz')) {
    $souborVerejnehoNastaveni = __DIR__ . '/verejne-nastaveni-beta.php';
} else if (str_ends_with($host, 'blackarrow.gamecon.cz')) {
    $souborVerejnehoNastaveni = __DIR__ . '/verejne-nastaveni-blackarrow.php';
} else if (str_ends_with($host, 'jakublounek.gamecon.cz')) {
    $souborVerejnehoNastaveni = __DIR__ . '/verejne-nastaveni-jakublounek.php';
} else if (str_ends_with($host, 'misahojna.gamecon.cz')) {
    $souborVerejnehoNastaveni = __DIR__ . '/verejne-nastaveni-misahojna.php';
} else if (str_ends_with($host, 'sciator.gamecon.cz')) {
    $souborVerejnehoNastaveni = __DIR__ . '/verejne-nastaveni-sciator.php';
} else if (in_array($host, ['admin.gamecon.cz', 'gamecon.cz', 'admin.vpsfree.gamecon.cz', 'vpsfree.gamecon.cz'])) {
    $souborVerejnehoNastaveni = __DIR__ . '/verejne-nastaveni-produkce.php';
} else {
    echo 'Nepodařilo se detekovat prostředí, nelze načíst nastavení verze';
    exit(1);
}

if ($souborVerejnehoNastaveni) {
    vytvorSouborSkrytehoNastaveniPodleEnv($souborVerejnehoNastaveni);
    require_once $souborVerejnehoNastaveni;
}

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
