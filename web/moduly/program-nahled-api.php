<?php

use Gamecon\Aktivita\Aktivita;

$this->bezStranky(true);

if(($idAktivity = get('idAktivity'))) {
  $a = Aktivita::zId($idAktivity);

  header('Content-type: application/json');
  $config = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

  echo json_encode([
    'nazev'     =>  $a->nazev(),
    'kratkyPopis' => $a->kratkyPopis(),
    'popis'     =>  $a->popis(),
    'obrazek'   =>  (string) $a->obrazek(),
    'vypraveci' =>  array_map(function($o) { return $o->jmenoNick(); }, $a->organizatori()),
    'stitky'    =>  array_map(function($s) { return mb_ucfirst($s); }, $a->tagy()),
    'cena'      =>  $a->cena(),
    'cas'       =>  $a->zacatek()->format('G') . ':00&ndash;' . $a->konec()->format('G') . ':00',
    'obsazenost'=>  $a->obsazenost(),
  ], $config);
}
