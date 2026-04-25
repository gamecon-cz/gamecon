<?php

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Cache\ProgramStaticFileGenerator;

require __DIR__ . '/../../nastaveni/zavadec.php';

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

ini_set('display_errors', true);
ini_set('error_reporting', E_ALL ^ E_STRICT);
ini_set('html_errors', false);
set_time_limit(600);

$systemoveNastaveni = SystemoveNastaveni::zGlobals();
$generator          = new ProgramStaticFileGenerator($systemoveNastaveni);
$aktualniRocnik     = $systemoveNastaveni->rocnik();
$programCacheDir    = $systemoveNastaveni->publicCacheDir() . '/program';

// Deploy vždy vynutí plnou regeneraci — regenerateAll() se sám ukončí, pokud
// už manifest existuje (ochrana před souběžnými requesty). Na deployi to ale
// znamená, že staré JSONy z předchozího releasu přežívají. Smažeme manifest,
// aby regenerateAll() vygeneroval nové soubory podle aktuálního kódu.
//
// Děláme to i pro předchozí dva ročníky — změna struktury JSONu (např. nová
// položka v generateActivities) se týká i archivního programu.
foreach (range($aktualniRocnik - 2, $aktualniRocnik) as $rok) {
    $manifestPath = $programCacheDir . "/manifest-{$rok}.json";
    if (file_exists($manifestPath)) {
        unlink($manifestPath);
    }
    $generator->regenerateAll($rok);
    $generator->cleanup($rok);
}

echo __FILE__ . ": Program cache regenerován pro ročníky " . ($aktualniRocnik - 2) . "–{$aktualniRocnik}.\n";
