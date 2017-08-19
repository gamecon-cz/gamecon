<?php

class Stranka extends DbObject {

  protected static $tabulka = 'stranky';
  protected static $pk = 'id_stranky';

  function html() {
    $raw = $this->htmlRaw();
    $raw = preg_replace_callback('@(<p>)?\(widget:([a-z\-]+)\)(</p>)?@', function($m) {
      $w = Widget::zNazvu($m[2]);
      if($w)  return $w->html();
      else    return 'widget neexistuje';
    }, $raw);
    return $raw;
  }

  protected function htmlRaw() {
    if(!isset($this->html)) {
      $this->html = markdownNoCache($this->r['obsah']);
    }
    return $this->html;
  }

  function nadpis() {
    preg_match('@<h1>([^<]+)</h1>@', $this->htmlRaw(), $m);
    return @$m[1];
  }

  function obrazek() {
    preg_match('@<img src="([^"]+)"[^>]*>@', $this->htmlRaw(), $m);
    return @$m[1];
  }

  function poradi() {
    return $this->r['poradi'];
  }

  function url() {
    return $this->r['url_stranky'];
  }

  static function zUrl($url = null) {
    if(!$url) $url = Url::zAktualni()->cela();
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
