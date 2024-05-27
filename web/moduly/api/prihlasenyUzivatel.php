<?php

// TODO: udělat REST api definice

use Gamecon\Api\ApiUzivatel;
use Gamecon\Api\Pomocne\ApiFunkce;

$this->bezStranky(true);
header('Content-type: application/json');

if ($_SERVER["REQUEST_METHOD"] != "POST") {
  return;
}

$u = Uzivatel::zSession();

$res = ApiUzivatel::apiUzivatel($u);
$json = ApiFunkce::vytvorApiJson($res);
echo $json;
