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
$generator = new ProgramStaticFileGenerator($systemoveNastaveni);
$generator->regenerateAll($systemoveNastaveni->rocnik());

echo __FILE__ . ": Program cache regenerován.\n";
