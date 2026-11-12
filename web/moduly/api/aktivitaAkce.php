<?php

/** @var Uzivatel|null $u */
/** @var Uzivatel|null $uPracovni */

/** @var Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\Lokace;
use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Cache\ProgramStaticFileGenerator;
use Gamecon\Kostymy\PujcovnaKostymu;

$response = [];

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    return;
}

$jsonConfig = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

// Na webu, kde se odhlásil nebo mu vypršelo přihlášení; v adminu nepřihlášeného odmítne už brána API.
// UI program bere 401 jako neúspěch s hláškou, prázdné tělo by nepřečetlo.
if (!$u) {
    http_response_code(401);
    header('Content-type: application/json');
    echo json_encode(['error' => '401 Unauthorized'], $jsonConfig);
    return;
}

// účastník, za kterého se jedná (v adminu pracovní uživatel), $u zůstává operátorem
if(!isset($uPracovni)) {
    $uPracovni = $u;
}

// Determine which activity is being acted on
$aktivitaId = post('prihlasit')
    ?: post('odhlasit')
        ?: post('prihlasSledujiciho')
            ?: post('odhlasSledujiciho')
                ?: null;

try {
    Aktivita::prihlasovatkoZpracujBezBack($uPracovni, $u);
    $response["úspěch"] = true;
} catch (Chyba $chyba) {
    $response["úspěch"] = false;
    $response["chyba"] = ["hláška" => $chyba->getMessage()];
}

// Enrich response with updated data for the affected activity
if ($aktivitaId) {
    $aktivitaId = (int)$aktivitaId;
    $aktivita = Aktivita::zId($aktivitaId);
    if ($aktivita) {
        $response['obsazenost'] = [
            'idAktivity' => $aktivita->id(),
            'obsazenost' => $aktivita->obsazenostObj(),
        ];

        // Stejný kontrakt jako aktivityUzivatel.php: všechna pole posílaná
        // vždy, nullable tam, kde "chybějící" má sémantický význam.
        // Za účastníka, ne za operátora: UI to zapíše do jeho programu.
        $jeOrganizator = $aktivita->organizuje($uPracovni);
        // místnosti stejně jako aktivityUzivatel.php: vidí je organizátor a ten, kdo aktivitu vede
        $seznamLokaci = $u->jeOrganizator() || $jeOrganizator
            ? array_map(static fn (Lokace $lokace) => $lokace->apiLokace(), $aktivita->seznamLokaci())
            : [];

        $response['aktivitaUzivatel'] = [
            'id'             => $aktivita->id(),
            'stavPrihlaseni' => StavPrihlaseni::frontendKod($aktivita->stavPrihlaseni($uPracovni)),
            'slevaNasobic'   => $aktivita->soucinitelCenyAktivity($uPracovni),
            'mistnosti'      => $seznamLokaci !== [] ? $seznamLokaci : null,
            'vedu'           => $jeOrganizator,
            // po přihlášení jde půjčit kostým, odhlášení rezervaci zruší
            'kostym'         => PujcovnaKostymu::zSystemovehoNastaveni($systemoveNastaveni)
                    ->kostymyVProgramu($uPracovni, $aktivita->rok())[$aktivita->id()] ?? null,
        ];
    }
}

// Dirty flag is already touched by Aktivita::prihlas()/odhlas(), just start worker
(new ProgramStaticFileGenerator($systemoveNastaveni))->tryStartWorker();

header('Content-type: application/json');
echo json_encode($response, $jsonConfig);
