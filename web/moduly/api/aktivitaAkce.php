<?php

/** @var Uzivatel $u */

/** @var Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Cache\ProgramStaticFileGenerator;

$u = Uzivatel::zSession();
$this->bezStranky(true);
$response = [];

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    return;
}

if (!$u) {
    return;
}

// Determine which activity is being acted on
$aktivitaId = post('prihlasit')
    ?: post('odhlasit')
        ?: post('prihlasSledujiciho')
            ?: post('odhlasSledujiciho')
                ?: null;

try {
    Aktivita::prihlasovatkoZpracujBezBack($u, $u);
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

        $uzivatelData = ['id' => $aktivita->id()];
        $stavPrihlasen = $aktivita->stavPrihlaseni($u);
        switch ($stavPrihlasen) {
            case StavPrihlaseni::PRIHLASEN:
                $uzivatelData['stavPrihlaseni'] = "prihlasen";
                break;
            case StavPrihlaseni::PRIHLASEN_A_DORAZIL:
                $uzivatelData['stavPrihlaseni'] = "prihlasenADorazil";
                break;
            case StavPrihlaseni::DORAZIL_JAKO_NAHRADNIK:
                $uzivatelData['stavPrihlaseni'] = "dorazilJakoNahradnik";
                break;
            case StavPrihlaseni::PRIHLASEN_ALE_NEDORAZIL:
                $uzivatelData['stavPrihlaseni'] = "prihlasenAleNedorazil";
                break;
            case StavPrihlaseni::POZDE_ZRUSIL:
                $uzivatelData['stavPrihlaseni'] = "pozdeZrusil";
                break;
            case StavPrihlaseni::SLEDUJICI:
                $uzivatelData['stavPrihlaseni'] = "sledujici";
                break;
        }
        $uzivatelData['slevaNasobic'] = $aktivita->soucinitelCenyAktivity($u);
        $uzivatelData['zamcenaMnou'] = $aktivita->zamcenoUzivatelem($u);
        $uzivatelData['zamcenaDo'] = $aktivita->tymZamcenyDo()?->getTimestamp() * 1000;
        $uzivatelData = array_filter($uzivatelData);
        $response['aktivitaUzivatel'] = $uzivatelData;
    }
}

// Dirty flag is already touched by Aktivita::prihlas()/odhlas(), just start worker
(new ProgramStaticFileGenerator($systemoveNastaveni))->tryStartWorker();

$jsonConfig = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
header('Content-type: application/json');
echo json_encode($response, $jsonConfig);
