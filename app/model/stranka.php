<?php

class Stranka extends DbObject {

  protected static $tabulka = 'stranky';
  protected static $pk = 'id_stranky';

  function html() {
    if(!isset($this->html)) {
      $this->html = markdownNoCache($this->r['obsah']);
    }
    return $this->html;
  }

  function nadpis() {
    preg_match('@<h1>([^<]+)</h1>@', $this->html(), $m);
    return @$m[1];
  }

  function obrazek() {
    preg_match('@<img src="([^"]+)"[^>]*>@', $this->html(), $m);
    return @$m[1];
  }

  function poradi() {
    return $this->r['poradi'];
  }

  function url() {
    return $this->r['url_stranky'];
  }

  static function zUrl() {
    $url = Url::zAktualni()->cela();
    return self::zWhereRadek('url_stranky = $1', [$url]);
  }

  /** Vrátí všechny stránky s url $prefix/něco */
  static function zUrlPrefixu($prefix) {
    return self::zWhere('url_stranky LIKE $1', [$prefix.'/%']);
  }

  static function zVsech() {
    return self::zWhere('1 ORDER BY url_stranky');
  }

}
