<?php
$souborVerejnehoNastaveni = null;
if (!empty($_COOKIE['unit_tests'])) {
    include __DIR__ . '/verejne-nastaveni-tests.php';
}
if (jsmeNaLocale()) {
    if (file_exists(__DIR__ . '/nastaveni-local.php')) {
        include __DIR__ . '/nastaveni-local.php'; // nepovinné lokální nastavení
    }
    require_once __DIR__ . '/nastaveni-local-default.php'; // výchozí lokální nastavení
} elseif (jsmeNaBete()) {
    $souborVerejnehoNastaveni = __DIR__ . '/verejne-nastaveni-beta.php';
} elseif (jsmeNaOstre()) {
    $souborVerejnehoNastaveni = __DIR__ . '/verejne-nastaveni-produkce.php';
} else {
    echo 'Nepodařilo se detekovat prostředí, nelze načíst nastavení verze';
    exit(1);
}

if ($souborVerejnehoNastaveni) {
    vytvorSouborSkrytehoNastaveniPodleEnv($souborVerejnehoNastaveni);
    require_once $souborVerejnehoNastaveni;
}
