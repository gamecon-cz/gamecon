<?php

class Lokace extends DbObject {

  protected static $tabulka = 'akce_lokace';
  protected static $pk = 'id_lokace';

  function __toString() {
    return $this->r['nazev'] . ', ' . $this->r['nazev_interni'] . ', ' . $this->r['dvere'];
  }

  function nazevInterni() {
    return $this->r['nazev_interni'];
  }

}
