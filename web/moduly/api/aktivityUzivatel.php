<?php

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Cache\DataSourcesCollector;
use Gamecon\Aktivita\FiltrAktivity;
use Gamecon\Cache\ProgramStaticFileGenerator;

/**
 * @var Uzivatel|null $u
 * @var Uzivatel|null $uPracovni
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

        // Všechna pole se posílají vždy — frontend typuje ApiAktivitaUživatel
        // jako fully-required (nullable tam, kde chybějící hodnota má
        // sémantický význam).
        $jeOrganizator = $aktivita->organizuje($u);
        $hlavniLokace  = $jeOrganizator
            ? $aktivita->hlavniLokace()
            : null;

        $aktivityUzivatelData[] = [
            'id'             => $aktivita->id(),
            'stavPrihlaseni' => StavPrihlaseni::frontendKod(
                $aktivita->stavPrihlaseni($u, $dataSourcesCollector),
            ),
            // slevaNasobic: 0 = 100% sleva, 1 = bez slevy. NEsmí být stripnuto.
            'slevaNasobic'   => $aktivita->soucinitelCenyAktivity($u, $dataSourcesCollector),
            // mistnost je orgovská vlastnost — null pro neorgany nebo pokud
            // aktivita nemá nastavenou hlavní lokaci.
            'mistnost'       => $hlavniLokace !== null ? (string) $hlavniLokace : null,
            'vedu'           => $jeOrganizator,
        ];

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
