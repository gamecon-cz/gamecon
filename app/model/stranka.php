<?php

class Stranka {

  protected $r;

  protected function __construct($r) {
    $this->r = $r;
  }

  static function form($id = null) {
    $f = new DbFormGc('stranky');
    if($id) {
      $s = Stranka::zId($id);
      $f->loadRow($s->r);
    }
    return $f;
  }

  function html() {
    return markdownNoCache($this->r['obsah']);
  }

  function id() {
    return $this->r['id_stranky'];
  }

  function url() {
    return $this->r['url_stranky'];
  }

  static function zId($id) {
    return self::zWhere('id_stranky = $1', array($id));
  }

  static function zUrl() {
    $url = Url::zAktualni()->cela();
    return self::zWhere('url_stranky = $1', array($url));
  }

  static function zVsech() {
    $o = dbQuery('SELECT * FROM stranky ORDER BY url_stranky');
    $a = array();
    while($r = mysql_fetch_assoc($o)) $a[] = new self($r);
    return $a;
  }

  protected static function zWhere($where, $params) {
    $r = dbOneLineS('SELECT * FROM stranky WHERE '.$where, $params);
    if($r) return new self($r);
    return null;
  }

}
