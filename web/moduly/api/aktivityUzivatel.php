<?php

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Cache\DataSourcesCollector;
use Gamecon\Aktivita\FiltrAktivity;
use Gamecon\Cache\ProgramStaticFileGenerator;

/**
 * @var Uzivatel|null $u
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    return;
}

$rok = array_key_exists('rok', $_GET)
    ? (int)$_GET['rok']
    : $systemoveNastaveni->rocnik();

$requestHash = $_GET['hash'] ?? '';

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

        // stavPrihlaseni — pouze pokud je uživatel nějak evidován u aktivity.
        // Pro přihlášené/sledující posíláme stringový kód; jinak necháme klíč nevyplněný.
        $stavPrihlasenMapa = [
            StavPrihlaseni::PRIHLASEN                => 'prihlasen',
            StavPrihlaseni::PRIHLASEN_A_DORAZIL      => 'prihlasenADorazil',
            StavPrihlaseni::DORAZIL_JAKO_NAHRADNIK   => 'dorazilJakoNahradnik',
            StavPrihlaseni::PRIHLASEN_ALE_NEDORAZIL  => 'prihlasenAleNedorazil',
            StavPrihlaseni::POZDE_ZRUSIL             => 'pozdeZrusil',
            StavPrihlaseni::SLEDUJICI                => 'sledujici',
        ];
        $stavPrihlasen = $aktivita->stavPrihlaseni($u, $dataSourcesCollector);
        if (isset($stavPrihlasenMapa[$stavPrihlasen])) {
            $aktivitaRes['stavPrihlaseni'] = $stavPrihlasenMapa[$stavPrihlasen];
        }

        // slevaNasobic MUSÍ být vždy poslán, když je vypočítán — frontend
        // rozlišuje 0 (100% sleva) od "undefined" (bez slevy). array_filter
        // by 0 smazal.
        $aktivitaRes['slevaNasobic'] = $aktivita->soucinitelCenyAktivity($u, $dataSourcesCollector);

        if ($u && $aktivita->organizuje($u)) {
            $aktivitaRes['vedu'] = true;
        }
        if ($aktivita->zamcenoUzivatelem($u)) {
            $aktivitaRes['zamcenaMnou'] = true;
        }
        $zamcenaDo = $aktivita->tymZamcenyDo()?->getTimestamp();
        if ($zamcenaDo !== null) {
            $aktivitaRes['zamcenaDo'] = $zamcenaDo * 1000;
        }

        $aktivityUzivatelData[] = $aktivitaRes;

        // Hidden activities visible only to this user (not publicly visible).
        // Struktura MUSÍ odpovídat ProgramStaticFileGenerator::generateActivities —
        // frontend typuje aktivitySkryte jako ApiAktivitaNepřihlášen[] a slučuje
        // je se statickými soubory. Proto používáme sdílený helper, aby se obě
        // cesty nemohly rozejít.
        if (!$aktivita->viditelnaPro(null)) {
            Aktivita::organizatoriDSC($dataSourcesCollector);
            $aktivitySkryteData[] = ProgramStaticFileGenerator::aktivitaDoPole($aktivita, $dataSourcesCollector);
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
