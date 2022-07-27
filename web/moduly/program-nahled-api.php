<?php

use Gamecon\Aktivita\Aktivita;

$this->bezStranky(true);

if (($idAktivity = get('idAktivity'))) {
    $a = Aktivita::zId($idAktivity);

    header('Content-type: application/json');

    if (!$a) {
        header('HTTP/1.0 404 Not Found');
        return;
    }

    echo json_encode([
        'nazev'       => $a->nazev(),
        'kratkyPopis' => $a->kratkyPopis(),
        'popis'       => $a->popis(),
        'obrazek'     => (string)$a->obrazek(),
        'vypraveci'   => array_map(
            static function ($o) {
                return $o->jmenoNick();
            },
            $a->organizatori()
        ),
        'stitky'      => array_map('mb_ucfirst', $a->tagy()),
        'cena'        => $a->cena(),
        'cas'         => $a->zacatek()->format('G') . ':00&ndash;' . $a->konec()->format('G') . ':00',
        'obsazenost'  => $a->obsazenost(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}
