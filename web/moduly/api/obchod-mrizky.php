<?php

// TODO: openapi definice

/*
 
*/

$this->bezStranky(true);
header('Content-type: application/json');
$config = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;


if ($_SERVER["REQUEST_METHOD"] == "POST") {
  // TODO: ukladání objektů musí mít správně udělaný escaping a zabezpečení
  $body = postBody();
  foreach ($body as &$mrizkaRaw) {
    $mrizka = ObchodMrizka::novy($mrizkaRaw);
    $mrizka->uloz();
  }
  die();
}

$vsechny = ObchodMrizka::zVsech();
$res = [];


echo json_encode($res, $config);
