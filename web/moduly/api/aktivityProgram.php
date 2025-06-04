<?php

// TODO: při pojmenování jako api/aktivity.php z nezámeho důvodu připisuje obsah aktivity.php
// TODO: udělat REST api definice

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Cache\DataSourcesCollector;
use Gamecon\Aktivita\FiltrAktivity;
use Gamecon\Aktivita\SqlStruktura\AkceTypySqlStruktura;
use Gamecon\SystemoveNastaveni\SqlStruktura\SystemoveNastaveniSqlStruktura;

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

$rok = array_key_exists('rok', $_GET)
    ? (int)$_GET['rok']
    : $systemoveNastaveni->rocnik();

$jeZapnuteCachovaniApiOdpovedi = $systemoveNastaveni->jeZapnuteCachovaniApiOdpovedi();

$tableDataDependentCache = $systemoveNastaveni->tableDataDependentCache();
// has to fetch all data versions before data itself, because after that we could fetch invalidly new, by some other process changed version and that would cache old data under new version
$tableDataDependentCache->preloadTableDataVersions();

$vytvorCachovanyDotaz = function (
    string $cacheKey,
    DataSourcesCollector $dataSourcesCollector,
    callable $dotahniData,
    // todo: tady se předá hash z requestu
    string $requestHash = "",
) use ($tableDataDependentCache, $jeZapnuteCachovaniApiOdpovedi) {
    $cachedItem = $tableDataDependentCache->getItem($cacheKey);
    $cached = true;

    if (!$cachedItem) {
        $dataNove = $dotahniData($dataSourcesCollector);
        $cached = false;

        if ($jeZapnuteCachovaniApiOdpovedi) {
            $cachedItem = $tableDataDependentCache->setItem(
                $cacheKey,
                $dataNove,
                $dataSourcesCollector,
            );
        } else {
            return [
                "data" => $dataNove,
                "hash" => "",
                "cached" => $cached,
            ];
        }
    }

    return [
        "data" => $cachedItem->data,
        "hash" => $cachedItem->hash,
        "cached" => $cached,
    ];
};

$dataSourcesCollector = new DataSourcesCollector();
// předpokládá se že se nebude měnit ale pro klid v duši přidáme
$dataSourcesCollector?->addDataSource(AkceTypySqlStruktura::AKCE_TYPY_TABULKA);
// systémové nastavení se používá na hodně místech, proto nemá smysl se ním zatěžovat do podrobna
$dataSourcesCollector?->addDataSource(SystemoveNastaveniSqlStruktura::SYSTEMOVE_NASTAVENI_TABULKA);

$aktivity = Aktivita::zFiltru(
    systemoveNastaveni: $systemoveNastaveni,
    filtr: [FiltrAktivity::ROK => $rok],
    prednacitat: true,
    dataSourcesCollector: $dataSourcesCollector,
);

$dotahniAktivityNeprihlasen = function (DataSourcesCollector $dataSourcesCollector) use ($aktivity, $u) {
    $aktivityNeprihlasen = [];
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
            $aktivita->organizatori(dataSourcesCollector: $dataSourcesCollector),
        );

        $stitkyId = $aktivita->tagyId();

        $aktivitaRes = [
            'id'            => $aktivita->id(),
            'nazev'         => $aktivita->nazev(),
            'kratkyPopis'   => $aktivita->kratkyPopis(),
            // vlastní cache, není dsc portože budeme porovnávat podle hashe
            'popisId'         => $aktivita->popisId(),
            // obrazek jak cachovat?
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
            'vdalsiVlne'    => $aktivita->vDalsiVlne($dataSourcesCollector),
            'probehnuta'    => $aktivita->probehnuta(),
            'jeBrigadnicka' => $aktivita->jeBrigadnicka(),
        ];

        if ($u) {
            $stavPrihlasen = $aktivita->stavPrihlaseni($u, $dataSourcesCollector);
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

            $aktivitaRes['slevaNasobic'] = $aktivita->slevaNasobic($u, $dataSourcesCollector);

            $aktivitaRes['vedu'] = $u && $aktivita->organizuje($u);
            // TODO: argumenty pro admin
            $aktivitaRes['zamcenaMnou'] = $aktivita->zamcenoUzivatelem($u);
        }
        $aktivitaRes['prihlasovatelna'] = $aktivita->prihlasovatelna();
        $aktivitaRes['zamcenaDo']       = $aktivita->tymZamcenyDo()?->getTimestamp() * 1000;
        $aktivitaRes['obsazenost']      = $aktivita->obsazenostObj($dataSourcesCollector);
        $aktivitaRes['tymova']          = $aktivita->tymova();

        $dite = $aktivita->detiIds();
        if ($dite && count($dite)) {
            $aktivitaRes['dite'] = $dite;
        }

        $aktivitaRes = array_filter($aktivitaRes);
        $aktivityNeprihlasen[]  = $aktivitaRes;
    }
    return $aktivityNeprihlasen;
};

$dotahniPopisyCachovane  = function () use ($aktivity, $u) {
    // todo:
    $cached = true;

    $popisyId = [];
    foreach ($aktivity as $aktivita) {
        $popisyId[] = $aktivita->popisId();
    }
    $hash = md5(json_encode($popisyId));

    $popisy = [];
    foreach ($aktivity as $aktivita) {
        $popisy[] = [
            "id" => $aktivita->popisId(),
            "popis" => $aktivita->popis(),
        ];
    }

    return [
        "data" => $popisy,
        "hash" => $hash,
        "cached" => $cached,
    ];
};

/*
 $cacheKey = 'aktivity_program-rocnik_' . $rok . '-' . ($u?->id() ?? 'anonym'),
 */

$response = [
    "aktivityNeprihlasen" => $vytvorCachovanyDotaz(
        ('aktivity_program-rocnik_' . $rok),
        $dataSourcesCollector->copy(),
        $dotahniAktivityNeprihlasen,
    ),
    "popisy" => $dotahniPopisyCachovane(),
];

header('Content-type: application/json');
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
