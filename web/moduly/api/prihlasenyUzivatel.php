<?php

/** @var Uzivatel $u */

header('Content-type: application/json');
$config = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  return;
}

$res = $u?->apiPrihlasenyUzivatel() ?? [];

echo json_encode($res, $config);
