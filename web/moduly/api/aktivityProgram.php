<?php

// TODO: při pojmenování jako api/aktivity.php z nezámeho důvodu připisuje obsah aktivity.php
// TODO: udělat REST api definice

use Gamecon\Cas\DateTimeCz;
use Gamecon\Aktivita\Aktivita;

$u = Uzivatel::zSession();

// TODO: remove tesing snippet: 
/*
var downloadAsJSON = (storageObj, name= "object") =>{
  const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(storageObj));
  const dlAnchorElem = document.createElement("a");
  dlAnchorElem.setAttribute("href",     dataStr     );
  dlAnchorElem.setAttribute("download", `${name}.json`);
  dlAnchorElem.click();
}

Promise.all(
  [2016, 2017, 2022]
    .map(rok => 
      fetch(`/web/api/aktivityProgram?rok=${rok}`, {method:"POST"})
        .then(x=>x.json())
        .catch(x=>[])
        .then(x=>[rok, x])
      )
  )
  .then(x=>Object.fromEntries(x))
  .then(x=>downloadAsJSON(x, "aktivityProgram"))

fetch("/web/api/aktivityProgram", {method:"POST"}).then(x=>x.text()).then(x=>console.log(x))
*/
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

  // TODO: opravit, padá v PrednacitaniTrait.php:93
  // $vypraveci = array_map(function ($o) {
  //   return $o->jmenoNick();
  // }, $a->organizatori());
  $stitky = array_map(function ($s) {
    return mb_ucfirst($s);
  }, $a->tagy());

  $aktivitaRes = [
    'id'        =>  $a->id(),
    'nazev'     =>  $a->nazev(),
    'kratkyPopis' => $a->kratkyPopis(),
    // 'popis'     =>  $a->popis(),
    'obrazek'   =>  (string) $a->obrazek(),
    // 'vypraveci' =>  $vypraveci,
    'stitky'    =>  $stitky,
    // TODO: cenaZaklad by měla být číslo ?
    'cenaZaklad'      => intval($a->cenaZaklad()),
    'casText'   =>  $a->zacatek() ? $a->zacatek()->format('G') . ':00&ndash;' . $a->konec()->format('G') . ':00' : "",
    'cas'        =>  $a->zacatek() ? [
      'od'         => $a->zacatek()->getTimestamp() * 1000,
      'do'         => $a->konec()->getTimestamp() * 1000,
    ] : null,
    'linie'      =>  $a->typ()->nazev(),
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

  $jeBrigadnicka = $a->jeBrigadnicka();
  if ($jeBrigadnicka)
    $aktivitaRes['jeBrigadnicka'] = $jeBrigadnicka;

  $dite = $a->detiIds();
  if ($dite && count($dite))
    $aktivitaRes['dite'] = $dite;

  $res[] = $aktivitaRes;
}


echo json_encode($res, $config);
