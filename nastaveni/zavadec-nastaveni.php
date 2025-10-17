<?php
$host = defined('SERVER_NAME')
    ? constant('SERVER_NAME')
    : ($_SERVER['SERVER_NAME'] ?? 'localhost');

$souborVerejnehoNastaveni = null;
if (!empty($_COOKIE['unit_tests'])) {
    include __DIR__ . '/verejne-nastaveni-tests.php';
}
if (jsmeNaLocale()) {
    if (file_exists(__DIR__ . '/nastaveni-local.php')) {
        include __DIR__ . '/nastaveni-local.php'; // nepovinné lokální nastavení
    }
    require_once __DIR__ . '/nastaveni-local-default.php'; // výchozí lokální nastavení
} elseif (str_ends_with($host, 'beta.gamecon.cz')) {
    $souborVerejnehoNastaveni = __DIR__ . '/verejne-nastaveni-beta.php';
} elseif (str_ends_with($host, 'blackarrow.gamecon.cz')) {
    $souborVerejnehoNastaveni = __DIR__ . '/verejne-nastaveni-blackarrow.php';
} elseif (str_ends_with($host, 'jakublounek.gamecon.cz')) {
    $souborVerejnehoNastaveni = __DIR__ . '/verejne-nastaveni-jakublounek.php';
} elseif (str_ends_with($host, 'misahojna.gamecon.cz')) {
    $souborVerejnehoNastaveni = __DIR__ . '/verejne-nastaveni-misahojna.php';
} elseif (str_ends_with($host, 'sciator.gamecon.cz')) {
    $souborVerejnehoNastaveni = __DIR__ . '/verejne-nastaveni-sciator.php';
} elseif (in_array($host, ['admin.gamecon.cz', 'gamecon.cz'], true)) {
    $souborVerejnehoNastaveni = __DIR__ . '/verejne-nastaveni-produkce.php';
} elseif (preg_match('~(?<rocnik>[0-9]{4})[.]gamecon[.]cz$~', $host, $matches)) {
    $souborVerejnehoNastaveni = __DIR__ . '/verejne-nastaveni-produkce.php';
} else {
    echo 'Nepodařilo se detekovat prostředí, nelze načíst nastavení verze';
    exit(1);
}

if ($souborVerejnehoNastaveni) {
    vytvorSouborSkrytehoNastaveniPodleEnv($souborVerejnehoNastaveni);
    require_once $souborVerejnehoNastaveni;
}
