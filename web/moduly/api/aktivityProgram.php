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

$body = null;
try {
    $bodyStr = file_get_contents("php://input");
    $body = json_decode($bodyStr);
} catch (Chyba $chyba) {
    // todo:
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
    string $requestHash = "",
) use (&$tableDataDependentCache, &$jeZapnuteCachovaniApiOdpovedi) {
    if (!$jeZapnuteCachovaniApiOdpovedi) {
        $dataNove = $dotahniData($dataSourcesCollector);
        return [
            "data" => $dataNove,
            "hash" => "",
            "cached" => false,
            // "tabulky" => $dataSourcesCollector->getDataSources(),
        ];
    }

    $cachedItem = $tableDataDependentCache->getItem($cacheKey);
    $cached = true;

    if (!$cachedItem) {
        $dataNove = $dotahniData($dataSourcesCollector);
        $cached = false;

        $cachedItem = $tableDataDependentCache->setItem(
            $cacheKey,
            $dataNove,
            $dataSourcesCollector,
        );
    }

    $vysledek = [
        "hash" => $cachedItem->hash,
        "cached" => $cached,
        // "tabulky" => $dataSourcesCollector->getDataSources(),
    ];

    if ($requestHash === "" || $requestHash !== $cachedItem->hash) {
        $vysledek["data"] = $cachedItem->data;
    }

    return $vysledek;
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

$skryteAktivityViditelnePro = [null];

$dotahniAktivityNeprihlasen = function (DataSourcesCollector $dataSourcesCollector) use (&$aktivity, &$skryteAktivityViditelnePro) {
    Aktivita::organizatoriDSC($dataSourcesCollector);

    $aktivityNeprihlasen = [];
    foreach ($aktivity as $aktivita) {
        $zacatekAktivity = $aktivita->zacatek();
        $konecAktivity   = $aktivita->konec();

        $verejneViditelna = $aktivita->viditelnaPro(null);
        $viditelnaPouzeProUzivatele = $aktivita->viditelnaPro($skryteAktivityViditelnePro[0] ?? null);
        // pokud je uživatel přihlášený tak to znamená že cheme poslat specificky pro něj jen skryté aktivity které vidí
        $viditelna = (
            ($verejneViditelna && ($skryteAktivityViditelnePro[0] === null))
            || (!$verejneViditelna && $viditelnaPouzeProUzivatele)
        );

        if (!$zacatekAktivity || !$konecAktivity || !$viditelna) {
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
            'vdalsiVlne'    => $aktivita->vDalsiVlne(),
            'probehnuta'    => $aktivita->probehnuta(),
            'jeBrigadnicka' => $aktivita->jeBrigadnicka(),
        ];

        $aktivitaRes['prihlasovatelna'] = $aktivita->prihlasovatelna();
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

$dotahniAktivityUzivatel = function (DataSourcesCollector $dataSourcesCollector) use (&$aktivity, &$u) {
    Aktivita::stavPrihlaseniDSC($dataSourcesCollector);
    Aktivita::slevaNasobicDSC($dataSourcesCollector);

    $aktivityUzivatel = [];
    foreach ($aktivity as $aktivita) {
        $zacatekAktivity = $aktivita->zacatek();
        $konecAktivity   = $aktivita->konec();

        if (!$zacatekAktivity || !$konecAktivity || !$aktivita->viditelnaPro($u)) {
            continue;
        }

        $aktivitaRes = [
            'id'            => $aktivita->id(),
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
        $aktivitaRes['zamcenaDo']       = $aktivita->tymZamcenyDo()?->getTimestamp() * 1000;

        $aktivitaRes = array_filter($aktivitaRes);
        $aktivityUzivatel[]  = $aktivitaRes;
    }
    return $aktivityUzivatel;
};

$dotahniobsazenosti = function (DataSourcesCollector $dataSourcesCollector) use (&$aktivity) {
    Aktivita::obsazenostObjDSC($dataSourcesCollector);

    $aktivityObsazenost = [];
    foreach ($aktivity as $aktivita) {
    $aktivityObsazenost[] = [
        'idAktivity' => $aktivita->id(),
        'obsazenost' => $aktivita->obsazenostObj($dataSourcesCollector),
    ];
    }
    return $aktivityObsazenost;
};


// tady je potřeba cachovat trochu jinak. MD už jsou samy o sobě cachované a z hashe víme jestli se nezměnili. Proto stačí spojit dohromady všechny hashe a udělat si hash z toho a víme jestli nedošlo ke změně popisu nějaké aktivity
$dotahniPopisyCachovane  = function () use (&$aktivity, &$body) {
    // todo:
    $cached = true;
    $puvodniHash = $body?->hashe?->popisy ?? null;

    $popisyId = [];
    foreach ($aktivity as $aktivita) {
        $popisyId[] = $aktivita->popisId();
    }
    // zajistí pořadí když neřadíme aktivity pro stejný hash nezávisle na poŕadí v jakém nám DB aktivity vrátí
    sort($popisyId);
    $hash = md5(json_encode($popisyId));

    $vysledek = [
        "hash" => $hash,
        "cached" => $cached,
    ];

    if (!$puvodniHash || $puvodniHash === "" || $puvodniHash !== $hash) {
        $popisy = [];
        foreach ($aktivity as $aktivita) {
            $popisy[] = [
                "id" => $aktivita->popisId(),
                "popis" => $aktivita->popis(),
            ];
        }
        $vysledek["data"] = $popisy;
    }

    return $vysledek;
};


$aktivityNeprihlasen = $vytvorCachovanyDotaz(
    ('aktivity_program-rocnik_' . "aktivityNeprihlasen" . "_" . $rok),
    $dataSourcesCollector->copy(),
    $dotahniAktivityNeprihlasen,
    $body?->hashe?->aktivityNeprihlasen ?? "",
);

// na druhé volání dotahniAktivityNeprihlasen chci pouze aktivity které vidí uživatel ale ne veřejné
$skryteAktivityViditelnePro[0] = $u;

$response = [
    "aktivityNeprihlasen" => $aktivityNeprihlasen,
    "aktivitySkryte" => $vytvorCachovanyDotaz(
        ('aktivity_program-rocnik_' . "aktivitySkryte" . "_" . $rok . '-' . ($u?->id() ?? 'anonym')),
        $dataSourcesCollector->copy(),
        $dotahniAktivityNeprihlasen,
        $body?->hashe?->aktivityNeprihlasen ?? "",
    ),
    "aktivityUživatel" => $vytvorCachovanyDotaz(
        ('aktivity_program-rocnik_' . "aktivityUživatel" . "_" . $rok . '-' . ($u?->id() ?? 'anonym')),
        $dataSourcesCollector->copy(),
        $dotahniAktivityUzivatel,
        $body?->hashe?->aktivityNeprihlasen ?? "",
    ),
    "obsazenosti" => $vytvorCachovanyDotaz(
        ('aktivity_program-rocnik_' . "obsazenosti" . "_" . $rok),
        $dataSourcesCollector->copy(),
        $dotahniobsazenosti,
        $body?->hashe?->obsazenosti ?? "",
    ),
    "popisy" => $dotahniPopisyCachovane(),
];

header('Content-type: application/json');
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
