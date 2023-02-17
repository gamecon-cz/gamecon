<?php

// TODO: udělat REST api definice

use Gamecon\Cas\DateTimeCz;

$u = Uzivatel::zSession();

$this->bezStranky(true);
header('Content-type: application/json');
$config = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

if ($_SERVER["REQUEST_METHOD"] != "POST") {
  return;
}



$res = [];

if ($u) {
  $res["prihlasen"] = true;
  $res["pohlavi"] = $u->pohlavi();
  $res["koncovkaDlePohlavi"] = $u->koncovkaDlePohlavi();

  if ($u->jeOrganizator()) {
    $res["organizator"] = true;
  }
  if ($u->jeBrigadnik()) {
    $res["brigadnik"] = true;
  }

  $res["gcStav"] = "nepřihlášen";

  if ($u->gcPrihlasen()) {
    $res["gcStav"] = "přihlášen";
  } 
  if ($u->gcPritomen()) {
    $res["gcStav"] = "přítomen";
  }
  if ($u->gcOdjel()) {
    $res["gcStav"] = "odjel";
  }
}

echo json_encode($res, $config);
