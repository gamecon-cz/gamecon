<?php

// TODO: při pojmenování jako api/aktivity.php z nezámeho důvodu připisuje obsah aktivity.php
// TODO: udělat REST api definice

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\StavPrihlaseni;

/**
 * @var Uzivatel|null $u
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

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

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    return;
}

$response = [];

$rok = array_key_exists('rok', $_GET)
    ? (int)$_GET['rok']
    : $systemoveNastaveni->rocnik();

$aktivity = Aktivita::zFiltru(
    systemoveNastaveni: $systemoveNastaveni,
    filtr: ['rok' => $rok],
);

foreach ($aktivity as $aktivita) {
    $zacatekAktivity = $aktivita->zacatek();
    $konecAktivity   = $aktivita->konec();

    if (!$zacatekAktivity || !$konecAktivity || !$aktivita->viditelnaPro($u)) {
        continue;
    }

    $vypraveci = array_map(
        fn(
            Uzivatel $organizator,
        ) => $organizator->jmenoNick(),
        $aktivita->organizatori(),
    );

    $stitkyId = $aktivita->tagyId();

    $aktivitaRes = [
        'id'            => $aktivita->id(),
        'nazev'         => $aktivita->nazev(),
        'kratkyPopis'   => $aktivita->kratkyPopis(),
        'popis'         => $aktivita->popis(),
        'obrazek'       => (string)$aktivita->obrazek(),
        'vypraveci'     => $vypraveci,
        'stitkyId'      => $stitkyId,
        // TODO: cenaZaklad by měla být číslo ?
        'cenaZaklad'    => intval($aktivita->cenaZaklad()),
        'casText'       => $zacatekAktivity
            ? $zacatekAktivity->format('G') . ':00&ndash;' . $konecAktivity->format('G') . ':00'
            : '',
        'cas'           => [
            'od' => $zacatekAktivity->getTimestamp() * 1000,
            'do' => $konecAktivity->getTimestamp() * 1000,
        ],
        'linie'         => $aktivita->typ()->nazev(),
        'vBudoucnu'     => $aktivita->vBudoucnu(),
        'vdalsiVlne'    => $aktivita->vDalsiVlne(),
        'probehnuta'    => $aktivita->probehnuta(),
        'jeBrigadnicka' => $aktivita->jeBrigadnicka(),
    ];

    if ($u) {
        $stavPrihlasen = $aktivita->stavPrihlaseni($u);
        switch ($stavPrihlasen) {
            case StavPrihlaseni::PRIHLASEN:
                $aktivitaRes['stavPrihlaseni'] = "prihlasen";
                break;
            case StavPrihlaseni::PRIHLASEN_A_DORAZIL:
                $aktivitaRes['stavPrihlaseni'] = "prihlasenADorazil";
                break;
            case StavPrihlaseni::DORAZIL_JAKO_NAHRADNIK:
                $aktivitaRes['stavPrihlaseni'] = "dorazilJakoNahradnik";
                break;
            case StavPrihlaseni::PRIHLASEN_ALE_NEDORAZIL:
                $aktivitaRes['stavPrihlaseni'] = "prihlasenAleNedorazil";
                break;
            case StavPrihlaseni::POZDE_ZRUSIL:
                $aktivitaRes['stavPrihlaseni'] = "pozdeZrusil";
                break;
            case StavPrihlaseni::SLEDUJICI:
                $aktivitaRes['stavPrihlaseni'] = "sledujici";
                break;
        }

        $aktivitaRes['slevaNasobic'] = $aktivita->slevaNasobic($u);

        $aktivitaRes['vedu'] = $u && $u->organizuje($aktivita);
        // TODO: argumenty pro admin
        $aktivitaRes['zamcenaMnou'] = $aktivita->zamcenoUzivatelem($u);
    }
    $aktivitaRes['prihlasovatelna'] = $aktivita->prihlasovatelna();
    $aktivitaRes['zamcenaDo']       = $aktivita->tymZamcenyDo()?->getTimestamp() * 1000;
    $aktivitaRes['obsazenost']      = $aktivita->obsazenostObj();
    $aktivitaRes['tymova']          = $aktivita->tymova();

    $dite = $aktivita->detiIds();
    if ($dite && count($dite))
        $aktivitaRes['dite'] = $dite;

    $aktivitaRes = array_filter($aktivitaRes);
    $response[]  = $aktivitaRes;
}

$jsonConfig = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
header('Content-type: application/json');
echo json_encode($response, $jsonConfig);
