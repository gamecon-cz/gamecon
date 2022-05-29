<?php

/*
API endpoint /api/program/roky
*/

$this->bezStranky(true);
header('Content-type: application/json');
$config = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

$roky = range(2009, ROK - 1);
$indexCovidRoku = array_search(2020, $roky);
unset($roky[$indexCovidRoku]);

$res = $roky;

echo json_encode($res, $config);
