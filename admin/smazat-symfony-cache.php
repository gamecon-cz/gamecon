<?php

require __DIR__ . '/../nastaveni/zavadec-zaklad.php';

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

// smazat Symfony cache
$symfonyCachePath = SYMFONY_CACHE_DIR;

if (!is_dir($symfonyCachePath)) {
    echo "admin/smazat-symfony-cache.php: Adresář Symfony cache neexistuje: $symfonyCachePath\n";
    exit(0);
}

$symfonyCacheWildcard = $symfonyCachePath . '/*';
// smazat obsah cache pomocí rm -rf (rychlejší než PHP funkce)
$command = sprintf('rm -rf %s', escapeshellarg($symfonyCacheWildcard));
exec($command, $output, $returnCode);

if ($returnCode !== 0) {
    http_response_code(500);
    die("Chyba: Nepodařilo se smazat Symfony cache. Návratový kód: $returnCode\n");
}

// informovat, že skript doběhl
echo "admin/smazat-symfony-cache.php: Symfony cache smazána.\n";
