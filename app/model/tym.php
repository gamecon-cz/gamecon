<?php

/**
 * Abstrakce týmu na aktivitě
 */
class Tym {

  private $a; // (primární) aktivita ke které tým patří
  private $r; // db řádek s aktivitou

  private static $aktivityId;

  /**
   * Toto je pouze rychle nahákovaný způsob vytváření týmu. Pokud bychom ho
   * používali na více místech, je potřeba vymyslet jak správně ukládat věci
   * v db a jak je spolu s aktivitou ne/tahat (viz také orm)
   */
  function __construct(Aktivita $a, array $r) {
    $this->a = $a;
    $this->r = $r;
  }

  /** Vrací číslo družiny (zvyk z DrD) a to generuje z ID aktivity */
  function cislo() {
    $typ = $this->a->typ();
    if(!isset(self::$aktivityId[$typ])) {
      self::$aktivityId[$typ] = explode(',', dbOneCol(
        'SELECT GROUP_CONCAT(id_akce) FROM akce_seznam WHERE typ = $1 AND rok = $2', [$typ, ROK]
      ));
    }
    return array_search($this->a->id(), self::$aktivityId[$typ]) + 1;
  }

  function nazev() {
    return $this->r['team_nazev'];
  }

}
