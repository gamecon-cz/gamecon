<?php

/*
API endpoint /api/program?view=MujProgram
view možnosti:
view=rok&rok={číslo}
view=mujprogram (jen POST)
view=vedu (jen POST)
*/

use Gamecon\Cas\DateTimeCz;

$this->bezStranky(true);
header('Content-type: application/json');
$config = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

$res = [];


$view = array_key_exists("view", $_GET) ? $_GET["view"] : "rok";
$rok = array_key_exists("rok", $_GET) ? intval($_GET["rok"], 10) : ROK;

$aktivity = Aktivita::zFiltru(["rok" => $rok]);

foreach ($aktivity as &$a) {
  $res[] = [
    'nazev'     =>  $a->nazev(),
    'kratkyPopis' => $a->kratkyPopis(),
    'popis'     =>  $a->popis(),
    'obrazek'   =>  (string) $a->obrazek(),
    'vypraveci' =>  array_map(function($o) { return $o->jmenoNick(); }, $a->organizatori()),
    'stitky'    =>  array_map(function($s) { return mb_ucfirst($s); }, $a->tagy()),
    'cena'      =>  $a->cena(),
    'cas'       =>  $a->zacatek() ? $a->zacatek()->format('G') . ':00&ndash;' . $a->konec()->format('G') . ':00' : "",
    'obsazenost'=>  $a->obsazenost(),
  ];
}


echo json_encode($res, $config);
