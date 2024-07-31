<?php

namespace Gamecon\Api\Pomocne;

class ApiFunkce {
  static function vytvorApiJson($obj) {
    $config = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    $json = json_encode($obj, $config);
    return $json;
  }
  
  static function etagZApiJson(string $string){
    return md5($string);
  }
}