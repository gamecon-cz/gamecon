<?php

/**
 * Typ aktivit (programová linie)
 */

class Typ {

  protected $r;

  protected function __construct($r) {
    $this->r = $r;
  }

  function id() {
    return $this->r['id_typu'];
  }

  function nazev() {
    return $this->r['typ_1pmn'];
  }

  function oTypu() {
    $s = Stranka::zId($this->r['stranka_o']);
    return $s ? $s->html() : null;
  }

  static function zUrl($url = null) {
    if($url === null) $url = Url::zAktualni()->cela();
    $r = dbOneLineS('SELECT * FROM akce_typy WHERE url_typu_mn = $1', array($url));
    if($r) return new self($r);
    return null;
  }

  static function zVsech() {
    $a = [];
    $o = dbQuery('SELECT * FROM akce_typy ORDER BY id_typu');
    while($r = mysql_fetch_assoc($o))
      $a[] = new self($r);
    return $a;
  }

}
