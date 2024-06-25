<?php

// TODO: při pojmenování jako api/aktivity.php z nezámeho důvodu připisuje obsah aktivity.php
// TODO: udělat REST api definice

use Gamecon\Cas\DateTimeCz;
use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Api\Pomocne\ApiFunkce;

use function Gamecon\Api\vytvorApiJson;

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

// if ($_SERVER["REQUEST_METHOD"] != "POST") {
//   return;
// }

$res = [];

$rok = array_key_exists("rok", $_GET) ? intval($_GET["rok"], 10) : ROK;

$aktivity = Aktivita::zFiltru(["rok" => $rok]);

foreach ($aktivity as &$a) {
  if (!$a->zacatek()) continue;
  if (!$a->viditelnaPro($u)) continue;

  $aktivitaRes = [
    'id'        =>  $a->id(),
  ];

  if ($u) {
    $stavPrihlasen = $a->stavPrihlaseni($u);

    if ($stavPrihlasen == StavPrihlaseni::PRIHLASEN) $aktivitaRes['stavPrihlaseni'] = 'prihlasen';
    if ($stavPrihlasen == StavPrihlaseni::PRIHLASEN_A_DORAZIL) $aktivitaRes['stavPrihlaseni'] = 'prihlasenADorazil';
    if ($stavPrihlasen == StavPrihlaseni::DORAZIL_JAKO_NAHRADNIK) $aktivitaRes['stavPrihlaseni'] = 'dorazilJakoNahradnik';
    if ($stavPrihlasen == StavPrihlaseni::PRIHLASEN_ALE_NEDORAZIL) $aktivitaRes['stavPrihlaseni'] = 'prihlasenAleNedorazil';
    if ($stavPrihlasen == StavPrihlaseni::POZDE_ZRUSIL) $aktivitaRes['stavPrihlaseni'] = 'pozdeZrusil';
    if ($stavPrihlasen == StavPrihlaseni::SLEDUJICI) $aktivitaRes['stavPrihlaseni'] = 'sledujici';
  }

  $slevaNasobic = $a->slevaNasobic($u);
  if ($slevaNasobic != 1) {
    $aktivitaRes['slevaNasobic'] = $slevaNasobic;
  }

  $vedu = $u && $u->organizuje($a);
  if ($vedu) {
    $aktivitaRes['vedu'] = $vedu;
  }

  // TODO: argumenty pro admin
  $prihlasovatelna = $a->prihlasovatelna();
  if ($prihlasovatelna) {
    $aktivitaRes['prihlasovatelna'] = $prihlasovatelna;
  }

  $zamcena = $a->zamcena();
  if ($zamcena) {
    $aktivitaRes['zamcena'] = $zamcena;
  }

  $aktivitaRes['obsazenost'] = $a->obsazenostObj();

  $tymova = $a->tymova();
  if ($tymova) {
    $aktivitaRes['tymova'] = $tymova;
  }

  $res[] = $aktivitaRes;
}


$json = ApiFunkce::vytvorApiJson($res);
echo $json;

