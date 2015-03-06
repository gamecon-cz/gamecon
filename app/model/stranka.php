<?php

class Stranka {

  protected $r;

  protected function __construct($r) {
    $this->r = $r;
  }

  function bezHlavicky() {
    $t = $this->html();
    $t = preg_replace('@<h1>[^<]+</h1>@', '', $t, 1);
    $t = preg_replace('@<img[^>]+>@', '', $t, 1);
    return $t;
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
    if(!isset($this->html)) {
      $this->html = markdownNoCache($this->r['obsah']);
    }
    return $this->html;
  }

  function id() {
    return $this->r['id_stranky'];
  }

  function nadpis() {
    preg_match('@<h1>([^<]+)</h1>@', $this->html(), $m);
    return @$m[1];
  }

  function obrazek() {
    preg_match('@<img src="([^"]+)"[^>]*>@', $this->html(), $m);
    return @$m[1];
  }

  function url() {
    return $this->r['url_stranky'];
  }

  static function zId($id) {
    return self::zWhereRadek('id_stranky = $1', array($id));
  }

  static function zUrl() {
    $url = Url::zAktualni()->cela();
    return self::zWhereRadek('url_stranky = $1', array($url));
  }

  /** Vrátí všechny stránky s url $prefix/něco */
  static function zUrlPrefixu($prefix) {
    return self::zWhere('url_stranky LIKE $1', [$prefix.'/%']);
  }

  static function zVsech() {
    return self::zWhere('1 ORDER BY url_stranky');
  }

  /** Pole řádků navrácené where */
  protected static function zWhere($where, $params = null) {
    $o = dbQuery('SELECT * FROM stranky WHERE '.$where, $params);
    $a = [];
    while($r = mysql_fetch_assoc($o)) $a[] = new self($r);
    return $a;
  }

  /** Nalezený řádek nebo null */
  protected static function zWhereRadek($where, $params) {
    $o = self::zWhere($where, $params);
    if($o) return $o[0];
    else return null;
  }

}
