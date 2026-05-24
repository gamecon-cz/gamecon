<?php

/** @var Uzivatel $u */
/** @var Uzivatel|null $uPracovni */

/** @var Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Cache\ProgramStaticFileGenerator;

$response = [];

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    return;
}

if (!$u) {
    return;
}

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
        $jeOrganizator = $aktivita->organizuje($u);
        $hlavniLokace  = $jeOrganizator
            ? $aktivita->hlavniLokace()
            : null;

        $response['aktivitaUzivatel'] = [
            'id'             => $aktivita->id(),
            'stavPrihlaseni' => StavPrihlaseni::frontendKod($aktivita->stavPrihlaseni($u)),
            'slevaNasobic'   => $aktivita->soucinitelCenyAktivity($u),
            'mistnost'       => $hlavniLokace !== null ? (string) $hlavniLokace : null,
            'vedu'           => $jeOrganizator,
        ];
    }
}

// Dirty flag is already touched by Aktivita::prihlas()/odhlas(), just start worker
(new ProgramStaticFileGenerator($systemoveNastaveni))->tryStartWorker();

$jsonConfig = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
header('Content-type: application/json');
echo json_encode($response, $jsonConfig);
