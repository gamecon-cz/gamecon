<?php

/**
 * Typ aktivit (programová linie)
 */

class Typ {

  protected $r;

  protected function __construct($r) {
    $this->r = $r;
  }

  /**
   * Vrátí letošní aktivity daného typu
   * @todo jen veřejné?
   */
  function aktivity() {
    return Aktivita::zFiltru(array(
      'rok' => ROK,
      'typ' => $this->id(),
    ), array('nazev_akce', 'zacatek', 'id_akce'));
  }

  function id() {
    return $this->r['id_typu'];
  }

  function oTypu() {
    $s = Stranka::zId($this->r['stranka_o']);
    return $s ? $s->html() : null;
  }

  static function zUrl() {
    $url = Url::zAktualni()->cela();
    $r = dbOneLineS('SELECT * FROM akce_typy WHERE url_typu_mn = $1', array($url));
    if($r) return new self($r);
    return null;
  }

}
