<?php

/** @var Uzivatel $u */
/** @var Uzivatel|null $uPracovni */

use Gamecon\Aktivita\Aktivita;

$response = [];

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    return;
}

if (!$u) {
    return ;
}

if(!isset($uPracovni)) {
    $uPracovni = $u;
}

try {
    Aktivita::prihlasovatkoZpracujBezBack($uPracovni, $u);
    $response["úspěch"] = true;
} catch (Chyba $chyba) {
    $response["úspěch"] = false;
    $response["chyba"] = ["hláška" => $chyba->getMessage()];
}

$jsonConfig = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
header('Content-type: application/json');
echo json_encode($response, $jsonConfig);
