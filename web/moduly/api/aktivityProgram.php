<?php

// TODO: při pojmenování jako api/aktivity.php z nezámeho důvodu připisuje obsah aktivity.php
// TODO: udělat REST api definice

use Gamecon\Cas\DateTimeCz;

$u = Uzivatel::zSession();

// TODO: je potřeba otestovat taky $u->gcPrihlasen() ?
// TODO: tohle nastavení by mělo platit pro všechny php soubory ve složce api
$this->bezStranky(true);
header('Content-type: application/json');
$config = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

if ($_SERVER["REQUEST_METHOD"] != "POST") {
  return;
}

$res = [];

$rok = array_key_exists("rok", $_GET) ? intval($_GET["rok"], 10) : ROK;

$aktivity = Aktivita::zFiltru(["rok" => $rok]);

foreach ($aktivity as &$a) {
  if (!$a->zacatek()) continue;
  if (!$a->viditelnaPro($u)) continue;

  $vypraveci = array_map(function ($o) {
    return $o->jmenoNick();
  }, $a->organizatori());
  $stitky = array_map(function ($s) {
    return mb_ucfirst($s);
  }, $a->tagy());

  $aktivitaRes = [
    'id'        =>  $a->id(),
    'nazev'     =>  $a->nazev(),
    'kratkyPopis' => $a->kratkyPopis(),
    'popis'     =>  $a->popis(),
    'obrazek'   =>  (string) $a->obrazek(),
    'vypraveci' =>  $vypraveci,
    'stitky'    =>  $stitky,
    // TODO: cenaZaklad by měla být číslo ?
    'cenaZaklad'      => intval($a->cenaZaklad()),
    'slevaNasobic' => $a->slevaNasobic($u),
    'casText'   =>  $a->zacatek() ? $a->zacatek()->format('G') . ':00&ndash;' . $a->konec()->format('G') . ':00' : "",
    'obsazenost' =>  $a->obsazenostObj(),
    'linie'      =>  $a->typ()->nazev(),
    'cas'        =>  $a->zacatek() ? [
      'od'         => $a->zacatek()->getTimestamp() * 1000,
      'do'         => $a->konec()->getTimestamp() * 1000,
    ] : null,
  ];

  $vBudoucnu = $a->vBudoucnu();
  if ($vBudoucnu)
    $aktivitaRes['vBudoucnu'] = $vBudoucnu;

  $vdalsiVlne = $a->vDalsiVlne();
  if ($vdalsiVlne)
    $aktivitaRes['vdalsiVlne'] = $vdalsiVlne;

  $probehnuta = $a->probehnuta();
  if ($probehnuta)
    $aktivitaRes['probehnuta'] = $probehnuta;

  $prihlasen = $u && $a->prihlasen($u);
  if ($u && $prihlasen)
    $aktivitaRes['prihlasen'] = $prihlasen;

  $slevaNasobic = $a->slevaNasobic($u);
  if ($slevaNasobic != 1)
    $aktivitaRes['slevaNasobic'] = $slevaNasobic;

  $vedu = $u && $u->organizuje($a);
  if ($vedu)
    $aktivitaRes['vedu'] = $vedu;

  $nahradnik = $u && $u->prihlasenJakoNahradnikNa($a);
  if ($nahradnik)
    $aktivitaRes['nahradnik'] = $nahradnik;


  $res[] = $aktivitaRes;
}


echo json_encode($res, $config);
