<?php

use Gamecon\Pravo;
use Gamecon\Role\SqlStruktura\PravoSqlStruktura;

/**
 * @var Uzivatel $u
 */

if (!$u->maPravo(Pravo::ADMINISTRACE_FINANCE))
{
    echo 'Sem nemůžeš, sem můžou jenom lidi s právem ' . Pravo::zId(Pravo::ADMINISTRACE_FINANCE)->raw()[PravoSqlStruktura::JMENO_PRAVA];
    exit;
}

require_once __DIR__ . '/_slevovy_poukaz.php';

$kod = vygenerujSlevovyKod($u->id());

header('Content-Type: image/png');
echo vykresliSlevovyPoukazPng($kod);
