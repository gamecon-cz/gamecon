<?php

use Gamecon\Aktivita\Aktivita;

$this->bezStranky(true);

if (!($idAktivity = get('idAktivity'))) {
    return;
}
$a = Aktivita::zId($idAktivity);

if (!$a) {
    header('HTTP/1.1 404 Not Found', true, 404);

    return;
}

header('Content-type: application/json');

echo json_encode([
    'nazev'       => $a->nazev(),
    'kratkyPopis' => $a->kratkyPopis(),
    'popis'       => $a->popis(),
    'obrazek'     => (string)$a->obrazek(),
    'vypraveci'   => array_map(
        static function (
            $o,
        ) {
            return $o->jmenoNaWebu();
        },
        $a->organizatori(),
    ),
    'stitky'      => array_map('mb_ucfirst', $a->tagy()),
    'cena'        => $a->cenaTextem(),
    'cas'         => $a->zacatek()->format('G:i') . '&ndash;' . $a->konec()->format('G:i'),
    'obsazenost'  => $a->obsazenost(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
