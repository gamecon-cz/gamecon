<?php

require __DIR__ . '/../nastaveni/zavadec.php';

if (HTTPS_ONLY) {
    httpsOnly();
}

// ověřit, že je posláno správné heslo
if (!post('migraceHeslo') || !MIGRACE_HESLO) {
    http_response_code(403);
    die("Chyba: Neznámé heslo migrace.\n");
}

// ověřit, že je posláno správné heslo
if (post('migraceHeslo') !== MIGRACE_HESLO) {
    http_response_code(403);
    die("Chyba: Je nutné zadat správné heslo v POST datech.\n");
}

// zapnout zobrazení chyb
ini_set('display_errors', true); // zobrazovat chyby obecně
ini_set('error_reporting', E_ALL ^ E_STRICT); // vybrat typy chyb k zobrazení
ini_set('html_errors', false); // chyby zobrazovat jako plaintext
set_time_limit(600);

$cacheDirs = [
    SYMFONY_CACHE_DIR,
    XTPL_CACHE_DIR,
    CACHE . '/css',
];

foreach ($cacheDirs as $cacheDir) {
    if (!is_dir($cacheDir)) {
        echo __FILE__ . ": Cache dir {$cacheDir} neexistuje\n";
        exit(0);
    }

    $cacheDirWildcard = $cacheDir . '/*';
    // smazat obsah cache pomocí rm -rf (rychlejší než PHP funkce)
    $command = sprintf('rm -rf %s', escapeshellarg($cacheDirWildcard));
    exec($command, $output, $returnCode);

    if ($returnCode !== 0) {
        http_response_code(500);
        echo "Chyba: Nepodařilo se smazat cache v {$cacheDir}. Návratový kód: $returnCode\n";
        exit(1);
    }
}

// informovat, že skript doběhl
echo __FILE__ . ": Cache smazána.\n";
