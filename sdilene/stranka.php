<?php

class Stranka {

  protected $r;

  protected function __construct($r) {
    $this->r = $r;
  }

  function html() {
    return markdownNoCache($this->r['obsah']);
  }

  static function zId($id) {
    return self::zWhere('id_stranky = $1', array($id));
  }

  static function zUrl() {
    $url = Url::zAktualni()->cela();
    return self::zWhere('url_stranky = $1', array($url));
  }

  protected static function zWhere($where, $params) {
    $r = dbOneLineS('SELECT * FROM stranky WHERE '.$where, $params);
    if($r) return new self($r);
    return null;
  }

}
