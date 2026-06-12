<?php

use Gamecon\Pravo;
use Gamecon\Role\SqlStruktura\PravoSqlStruktura;

/**
 * Vykreslí tiskový PNG poukaz pro již existující slevový kód (parametr ?id=).
 *
 * @var Uzivatel $u
 */

if (!$u->maPravo(Pravo::ADMINISTRACE_FINANCE))
{
    echo 'Sem nemůžeš, sem můžou jenom lidi s právem ' . Pravo::zId(Pravo::ADMINISTRACE_FINANCE)->raw()[PravoSqlStruktura::JMENO_PRAVA];
    exit;
}

require_once __DIR__ . '/_slevovy_poukaz.php';

$id  = (int)($_GET['id'] ?? 0);
$kod = $id
    ? dbOneCol('SELECT kod FROM slevove_kody WHERE id = $0', [$id])
    : null;

if (!$kod) {
    http_response_code(404);
    echo 'Slevový poukaz nenalezen.';
    exit;
}

header('Content-Type: image/png');
echo vykresliSlevovyPoukazPng($kod);
