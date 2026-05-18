<?php

use Gamecon\Cache\ProgramStaticFileGenerator;

/**
 * Generuje soubory do /cache/public/program
 *
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    return;
}

$rok = array_key_exists('rok', $_GET)
    ? (int) $_GET['rok']
    : $systemoveNastaveni->rocnik();

if ($rok < 2009 || $rok > $systemoveNastaveni->rocnik()) {
    http_response_code(400);
    header('Content-type: application/json');
    echo json_encode(['error' => "Neplatný ročník: {$rok}"]);
    return;
}

$programStaticFileGenerator = new ProgramStaticFileGenerator($systemoveNastaveni);

if ($programStaticFileGenerator->readManifest($rok) === null) {
    $programStaticFileGenerator->regenerateAll($rok);
}

$manifest = $programStaticFileGenerator->readManifest($rok);

if ($manifest === null) {
    http_response_code(500);
    header('Content-type: application/json');
    echo json_encode(['error' => "Nepodařilo se vygenerovat manifest pro rok {$rok}"]);
    return;
}

header('Content-type: application/json');
echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
