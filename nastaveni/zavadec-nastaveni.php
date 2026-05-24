<?php

use Gamecon\Prostredi\Prostredi;

if (!empty($_COOKIE['unit_tests'])) {
    include __DIR__ . '/verejne-nastaveni-tests.php';
}

if (file_exists(__DIR__ . '/nastaveni-server.php')) {
    include __DIR__ . '/nastaveni-server.php';
}

$prostredi = Prostredi::detect();

if ($prostredi === Prostredi::Locale) {
    // Locale is special: nastaveni-local.php is optional (developer
    // override) and nastaveni-local-default.php holds env-driven defaults.
    // Neither maps cleanly to the verejne-nastaveni-*.php pattern, so
    // it stays as a bespoke branch.
    if (file_exists(__DIR__ . '/nastaveni-local.php')) {
        include __DIR__ . '/nastaveni-local.php';
    }
    require_once __DIR__ . '/nastaveni-local-default.php';
} else {
    $souborVerejnehoNastaveni = $prostredi->souborVerejnehoNastaveni();
    vytvorSouborSkrytehoNastaveniPodleEnv($souborVerejnehoNastaveni);
    require_once $souborVerejnehoNastaveni;
}
