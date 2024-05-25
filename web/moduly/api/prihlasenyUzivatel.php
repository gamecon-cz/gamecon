<?php

// TODO: udělat REST api definice

use Gamecon\Api\Pomocne\ApiFunkce;

$u = Uzivatel::zSession();

$this->bezStranky(true);
header('Content-type: application/json');

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

$json = ApiFunkce::vytvorApiJson($res);
echo $json;
