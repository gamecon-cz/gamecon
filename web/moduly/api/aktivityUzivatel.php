<?php

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Cache\DataSourcesCollector;
use Gamecon\Aktivita\FiltrAktivity;

/**
 * @var Uzivatel|null $u
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    return;
}

$rok = array_key_exists('rok', $_GET)
    ? (int)$_GET['rok']
    : $systemoveNastaveni->rocnik();

$body = null;
try {
    $bodyStr = file_get_contents("php://input");
    $body = json_decode($bodyStr);
} catch (Chyba $chyba) {
}

$requestHash = $body?->hash ?? '';

$dataSourcesCollector = new DataSourcesCollector();

$aktivity = Aktivita::zFiltru(
    systemoveNastaveni: $systemoveNastaveni,
    filtr: [FiltrAktivity::ROK => $rok],
    prednacitat: true,
    dataSourcesCollector: $dataSourcesCollector,
);

// User-specific activity data
$aktivityUzivatelData = [];
$aktivitySkryteData = [];

if ($u) {
    Aktivita::stavPrihlaseniDSC($dataSourcesCollector);
    Aktivita::soucinitelCenyAktivityDSC($dataSourcesCollector);

    foreach ($aktivity as $aktivita) {
        $zacatekAktivity = $aktivita->zacatek();
        $konecAktivity = $aktivita->konec();

        if (!$zacatekAktivity || !$konecAktivity || !$aktivita->viditelnaPro($u)) {
            continue;
        }

        $aktivitaRes = [
            'id' => $aktivita->id(),
        ];

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

        $aktivitaRes['slevaNasobic'] = $aktivita->soucinitelCenyAktivity($u, $dataSourcesCollector);
        $aktivitaRes['vedu'] = $u && $aktivita->organizuje($u);
        $aktivitaRes['zamcenaMnou'] = $aktivita->zamcenoUzivatelem($u);
        $aktivitaRes['zamcenaDo'] = $aktivita->tymZamcenyDo()?->getTimestamp() * 1000;

        $aktivitaRes = array_filter($aktivitaRes);
        $aktivityUzivatelData[] = $aktivitaRes;

        // Hidden activities visible only to this user (not publicly visible)
        if (!$aktivita->viditelnaPro(null)) {
            Aktivita::organizatoriDSC($dataSourcesCollector);

            $vypraveci = array_map(
                fn(Uzivatel $organizator) => $organizator->jmenoNick(),
                $aktivita->organizatori(dataSourcesCollector: $dataSourcesCollector),
            );

            $stitkyId = $aktivita->tagyId();

            $skryta = [
                'id' => $aktivita->id(),
                'nazev' => $aktivita->nazev(),
                'kratkyPopis' => $aktivita->kratkyPopis(),
                'popisId' => $aktivita->popisId(),
                'obrazek' => (string)$aktivita->obrazek(),
                'vypraveci' => $vypraveci,
                'stitkyId' => $stitkyId,
                'cenaZaklad' => intval($aktivita->cenaZaklad()),
                'casText' => $zacatekAktivity
                    ? $zacatekAktivity->format('G') . ':00&ndash;' . $konecAktivity->format('G') . ':00'
                    : '',
                'cas' => [
                    'od' => $zacatekAktivity->getTimestamp() * 1000,
                    'do' => $konecAktivity->getTimestamp() * 1000,
                ],
                'linie' => $aktivita->typ()->nazev(),
                'vBudoucnu' => $aktivita->vBudoucnu(),
                'vdalsiVlne' => $aktivita->vDalsiVlne(),
                'probehnuta' => $aktivita->probehnuta(),
                'jeBrigadnicka' => $aktivita->jeBrigadnicka(),
                'prihlasovatelna' => $aktivita->prihlasovatelna(),
                'tymova' => $aktivita->tymova(),
            ];

            $dite = $aktivita->detiIds();
            if ($dite && count($dite)) {
                $skryta['dite'] = $dite;
            }

            $skryta = array_filter($skryta);
            $aktivitySkryteData[] = $skryta;
        }
    }
}

$responseData = [
    'aktivityUzivatel' => $aktivityUzivatelData,
    'aktivitySkryte' => $aktivitySkryteData,
];

$responseJson = json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$responseHash = md5($responseJson);

$response = [
    'hash' => $responseHash,
];

if ($requestHash === '' || $requestHash !== $responseHash) {
    $response['data'] = $responseData;
}

header('Content-type: application/json');
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
