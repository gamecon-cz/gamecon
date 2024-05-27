<?php

use Gamecon\Api\ApiStitky;
use Gamecon\Api\Pomocne\ApiFunkce;

$this->bezStranky(true);
header('Content-type: application/json');

if ($_SERVER["REQUEST_METHOD"] != "GET") {
  return;
}

$json = ApiFunkce::vytvorApiJson(ApiStitky::apiStitky());
$etag = ApiFunkce::etagZApiJson($json);

$ifNoneMatch = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : '';

if ($ifNoneMatch === $etag) {
    header("HTTP/1.1 304 Not Modified");
    exit();
}

header("Etag: $etag");
echo $json;
