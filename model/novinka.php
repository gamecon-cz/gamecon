<?php

class Novinka {

  protected $r;

  protected static $prvniObrazek = '@<img src="([^"]+)"@'; // RV odpovídající prvnímu obrázku v textu

  const NOVINKA = 1;
  const BLOG = 2;

  protected function __construct($r) {
    $this->r = $r;
  }

  function datum() {
    return date('j.n.', strtotime($this->r['vydat']));
  }

  function form() {
    $f = new DbFormGc('novinky');
    $f->loadRow($this->r);
    return $f;
  }

  function id() {
    return $this->r['id'];
  }

  /** Prvních $n znaků příspěvku */
  function nahled($n = 250) {
    return mb_substr(strip_tags($this->text()), 0, $n);
  }

  function nazev() {
    return $this->r['nazev'];
  }

  /** url obrázku příspěvku */
  function obrazek() {
    preg_match(self::$prvniObrazek, $this->text(), $m);
    return $m[1];
  }

  function text() {
    return dbMarkdown($this->r['text']);
  }

  function typSlovy() {
    $typy = array(
      self::BLOG => 'blog',
      self::NOVINKA => 'novinka',
    );
    return $typy[$this->r['typ']];
  }

  /** název enkódovaný do url formátu */
  function url() {
    return $this->r['url'];
  }

  static function zId($id) {
    return self::zWhere('id = $1', array($id));
  }

  static function zNejnovejsi($typ = self::NOVINKA) {
    return self::zWhere('vydat <= NOW() AND typ = $1 ORDER BY vydat DESC LIMIT 1', array($typ));
  }

  static function zUrl($url, $typ = self::NOVINKA) {
    return self::zWhere('url = $1 AND typ = $2', array($url, $typ));
  }

  static function zVsech() {
    $o = dbQuery('SELECT * FROM novinky ORDER BY vydat = 0 DESC, vydat DESC');
    $a = array();
    while($r = mysql_fetch_assoc($o)) $a[] = new self($r);
    return $a;
  }

  protected static function zWhere($where, $params = null) {
    $r = dbOneLineS('SELECT * FROM novinky WHERE '.$where, $params);
    if($r) return new self($r);
    else return null;
  }

}

