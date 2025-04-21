<?php

/** @var Uzivatel $u */

use Gamecon\Aktivita\Aktivita;

$u = Uzivatel::zSession();
$this->bezStranky(true);
$response = [];

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    return;
}

if (!$u) {
    return ;
}


try {
    Aktivita::prihlasovatkoZpracujBezBack($u, $u);
    $response["úspěch"] = true;
} catch (Chyba $chyba) {
    $response["úspěch"] = false;
    $response["chyba"] = ["hláška" => $chyba->getMessage()];
}

$jsonConfig = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
header('Content-type: application/json');
echo json_encode($response, $jsonConfig);
