<?php

class Novinka {

  protected $r;

  protected static $prvniObrazek = '@<img src="([^"]+)"@'; // RV odpovídající prvnímu obrázku v textu

  const NOVINKA = 1;
  const BLOG = 2;

  protected function __construct($r) {
    $this->r = $r;
  }

  function autor() {
    return preg_replace('@"(\S+)"@', '„$1“', $this->r['autor']);
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
    $sub = mb_substr(strip_tags($this->text()), 0, $n);
    if($sub[0] == '_') $sub[0] = ' ';
    return $sub;
  }

  function nazev() {
    return $this->r['nazev'];
  }

  /** url obrázku příspěvku */
  function obrazek() {
    preg_match(self::$prvniObrazek, $this->text(), $m);
    return @$m[1]; // TODO odstranit
  }

  function text() {
    return dbMarkdown($this->r['text']);
  }

  function typ() {
    return $this->r['typ'];
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

  function vydat() {
    if(empty($this->vydat)) $this->vydat = new DateTimeCz($this->r['vydat']);
    return $this->vydat;
  }

  static function zId($id) {
    return self::zWhereRadek('id = $1', array($id));
  }

  static function zNejnovejsi($typ = self::NOVINKA) {
    return self::zWhereRadek('vydat <= NOW() AND typ = $1 ORDER BY vydat DESC LIMIT 1', array($typ));
  }

  static function zNejnovejsich($start = 0, $limit = 20) {
    return self::zWhere('vydat <= NOW() ORDER BY vydat DESC LIMIT $1, $2', [$start, $limit]);
  }

  static function zTypu($typ) {
    return self::zWhere('vydat <= NOW() AND typ = $1 ORDER BY vydat DESC', [$typ]);
  }

  static function zUrl($url, $typ = self::NOVINKA) {
    return self::zWhere('url = $1 AND typ = $2', array($url, $typ))[0];
  }

  static function zVsech() {
    return self::zWhere('1 ORDER BY vydat = 0 DESC, vydat DESC');
  }

  protected static function zWhere($where, $params = null) {
    $o = dbQuery('SELECT * FROM novinky WHERE '.$where, $params);
    $a = array();
    while($r = mysql_fetch_assoc($o))
      $a[] = new self($r);
    return $a;
  }

  protected static function zWhereRadek($where, $params = null) {
    $o = self::zWhere($where, $params);
    if(empty($o)) return null;
    return $o[0];
  }

}

