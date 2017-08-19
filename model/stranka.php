<?php

class Stranka extends DbObject {

  protected static $tabulka = 'stranky';
  protected static $pk = 'id_stranky';

  function html() {
    if(!isset($this->html)) {
      $html = markdownNoCache($this->r['obsah']);
      $html = preg_replace_callback('@(<p>)?\(widget:([a-z\-]+)\)(</p>)?@', function($m) {
        $w = Widget::zNazvu($m[2]);
        if($w)  return $w->html();
        else    return 'widget neexistuje';
      }, $html);
      $this->html = $html;
    }
    return $this->html;
  }

  function nadpis() {
    $html = preg_quote_wildcard('<h1>~</h1>');
    $md   = '^#\s*([^#].+)$';
    preg_match("@$html|$md@m", $this->r['obsah'], $m);
    return @($m[1] ?: $m[2]);
  }

  function obrazek() {
    $html = preg_quote_wildcard('<img src="~"~>');
    $md   = preg_quote_wildcard('![~](~)');
    preg_match("@$html|$md@", $this->r['obsah'], $m);
    return @($m[1] ?: $m[4]);
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
