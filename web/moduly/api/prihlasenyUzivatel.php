<?php

header('Content-type: application/json');
$config = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
  return;
}

$res = Uzivatel::apiPrihlasenyUzivatel();

echo json_encode($res, $config);
