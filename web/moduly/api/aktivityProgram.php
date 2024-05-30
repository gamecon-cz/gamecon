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

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    return;
}

$response = [];

$rok = array_key_exists('rok', $_GET)
    ? (int)$_GET['rok']
    : ROCNIK;

$aktivity = Aktivita::zFiltru(["rok" => $rok]);

foreach ($aktivity as $aktivita) {
    if (!$aktivita->zacatek() || !$aktivita->konec() || !$aktivita->viditelnaPro($u)) {
        continue;
    }

    $vypraveci = array_map(
        fn(Uzivatel $organizator) => $organizator->jmenoNick(),
        $aktivita->organizatori()
    );

    $stitkyId = $aktivita->tagyId();

    $aktivitaRes = [
        'id'          => $aktivita->id(),
        'nazev'       => $aktivita->nazev(),
        'kratkyPopis' => $aktivita->kratkyPopis(),
        'popis'       => $aktivita->popis(),
        'obrazek'     => (string)$aktivita->obrazek(),
        'vypraveci'   => $vypraveci,
        'stitkyId'    => $stitkyId,
        // TODO: cenaZaklad by měla být číslo ?
        'cenaZaklad'  => intval($aktivita->cenaZaklad()),
        'casText'     => $aktivita->zacatek()
            ? $aktivita->zacatek()->format('G') . ':00&ndash;' . $aktivita->konec()->format('G') . ':00'
            : '',
        'cas'         => [
            'od' => $aktivita->zacatek()->getTimestamp() * 1000,
            'do' => $aktivita->konec()->getTimestamp() * 1000,
        ],
        'linie'       => $aktivita->typ()->nazev(),
    ];

    $vBudoucnu = $aktivita->vBudoucnu();
    if ($vBudoucnu)
        $aktivitaRes['vBudoucnu'] = $vBudoucnu;

    $vdalsiVlne = $aktivita->vDalsiVlne();
    if ($vdalsiVlne)
        $aktivitaRes['vdalsiVlne'] = $vdalsiVlne;

    $probehnuta = $aktivita->probehnuta();
    if ($probehnuta)
        $aktivitaRes['probehnuta'] = $probehnuta;

    $jeBrigadnicka = $aktivita->jeBrigadnicka();
    if ($jeBrigadnicka)
        $aktivitaRes['jeBrigadnicka'] = $jeBrigadnicka;

    $dite = $aktivita->detiIds();
    if ($dite && count($dite))
        $aktivitaRes['dite'] = $dite;

    $response[] = $aktivitaRes;
}

$jsonConfig = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
header('Content-type: application/json');
echo json_encode($response, $jsonConfig);
