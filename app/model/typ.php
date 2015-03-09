<?php

/**
 * Typ aktivit (programová linie)
 */
class Typ extends DbObject {

  protected static $tabulka = 'akce_typy';
  protected static $pk = 'id_typu';

  function nazev() {
    return $this->r['typ_1pmn'];
  }

  function oTypu() {
    $s = Stranka::zId($this->r['stranka_o']);
    return $s ? $s->html() : null;
  }

  function url() {
    return $this->r['url_typu'];
  }

  static function zUrl($url = null) {
    if($url === null) $url = Url::zAktualni()->cela();
    return self::zWhereRadek('url_typu_mn = $1', [$url]);
  }

  static function zVsech() {
    return self::zWhere('1 ORDER BY id_typu');
  }

}
