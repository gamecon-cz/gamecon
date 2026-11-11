<?php

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Aktivita\FiltrAktivity;
use Gamecon\Aktivita\Lokace;
use Gamecon\Cache\ProgramStaticFileGenerator;

/**
 * @var Uzivatel|null $u
 * @var Uzivatel|null $uPracovni
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

$operator = $u;
$ucastnik = $uPracovni ?? $u;

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    return;
}

$rok = array_key_exists('rok', $_GET)
    ? (int)$_GET['rok']
    : $systemoveNastaveni->rocnik();

$requestHash = $_GET['hash'] ?? '';

$aktivity = Aktivita::zFiltru(
    systemoveNastaveni: $systemoveNastaveni,
    filtr: [FiltrAktivity::ROK => $rok],
    prednacitat: true,
);

// User-specific activity data
$aktivityUzivatelData = [];
$aktivitySkryteData = [];

if ($ucastnik) {
    foreach ($aktivity as $aktivita) {
        $zacatekAktivity = $aktivita->zacatek();
        $konecAktivity = $aktivita->konec();

        if (!$zacatekAktivity || !$konecAktivity || !$aktivita->viditelnaPro($u)) {
            continue;
        }

        // Všechna pole se posílají vždy — frontend typuje ApiAktivitaUživatel
        // jako fully-required (nullable tam, kde chybějící hodnota má
        // sémantický význam).
        $vedeAktivitu = $aktivita->organizuje($ucastnik);
        $seznamLokaci  = $operator->jeOrganizator() || $vedeAktivitu
            ? array_map(function (Lokace $l) { return $l->apiLokace(); }, $aktivita->seznamLokaci())
            : [];

        $interni = !$aktivita->viditelnaPro(null);
        $stavPrihlaseni = $aktivita->stavPrihlaseni($ucastnik);
        $prihlasovatelna = $aktivita->prihlasovatelnaProPrihlasujiciho($operator ?? $ucastnik);
        // upozornění na věk jen tam, kde Přihlašovátko.tsx nabídne přihlášení (ne minulé ročníky, ne kde už je nebo vede)
        $muzeSePrihlasit = $prihlasovatelna
            && !$vedeAktivitu
            && in_array($stavPrihlaseni, [StavPrihlaseni::NEPRIHLASEN, StavPrihlaseni::SLEDUJICI], true);

        $aktivityUzivatelData[] = [
            'id'             => $aktivita->id(),
            'stavPrihlaseni' => StavPrihlaseni::frontendKod($stavPrihlaseni),
            'prihlasovatelna' => $prihlasovatelna,
            // slevaNasobic: 0 = 100% sleva, 1 = bez slevy. NEsmí být stripnuto.
            'slevaNasobic'   => $aktivita->soucinitelCenyAktivity($ucastnik),
            // mistnost je orgovská vlastnost — null pro neorgany nebo pokud
            // aktivita nemá nastavenou hlavní lokaci.
            'mistnosti'       => !empty($seznamLokaci) ? $seznamLokaci : null,
            'vedu'            => $vedeAktivitu,
            'interni'         => $interni,
            // {nadpis, text}, když účastníkovi v den aktivity ještě nebude její minimální věk, jinak null; ukáže se před přihlášením
            'upozorneniNaVek' => $muzeSePrihlasit
                ? $aktivita->getUpozorneniNaVek($ucastnik, $operator)
                : null,
        ];

        // Hidden activities visible only to this user (not publicly visible).
        // Struktura MUSÍ odpovídat ProgramStaticFileGenerator::generateActivities —
        // frontend typuje aktivitySkryte jako ApiAktivitaNepřihlášen[] a slučuje
        // je se statickými soubory. Proto používáme sdílený helper, aby se obě
        // cesty nemohly rozejít.
        if ($interni) {
            $aktivitySkryteData[] = ProgramStaticFileGenerator::aktivitaDoPole($aktivita);
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
