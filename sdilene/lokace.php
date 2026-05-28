<?php

class Lokace {

  protected $r;

  protected function __construct($r) {
    $this->r = $r;
  }

  function __toString() {
    return $this->r['nazev'] . ', ' . $this->r['nazev_interni'] . ', ' . $this->r['dvere'];
  }

  static function zId($id) {
    $r = dbOneLine('SELECT * FROM akce_lokace WHERE id_lokace = $1', array($id));
    if($r) {
      return new self($r);
    } else {
      return null;
    }
  }

}
