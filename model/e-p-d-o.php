<?php

/**
 * Extended PDO – databázová knihovna napodobující originální fce
 */
class EPDO extends PDO {

  /**
   * Vloží do tabulky daného názvu nový řádek definovaný jako asoc. pole
   */
  function insert($tabulka, $radek) {
    $sloupce = implode(',', array_map([$this, 'qi'], array_keys($radek)));
    $hodnoty = implode(',', array_map([$this, 'qv'], $radek));
    $this->query("INSERT INTO $tabulka ($sloupce) VALUES ($hodnoty)");
  }

  /**
   * Provede dotaz
   * @todo počítání času a podobně
   * @todo argumenty
   * @todo nějaký složitější systém výjimek na jemné ladění
   */
  function query($q, $args = null) {
    /*
    // inspirace pro argumenty preg style
    $delta = strpos($q, '$0')===false ? -1 : 0; // povolení číslování $1, $2, $3...
    return dbQuery(
      preg_replace_callback('~\$([0-9]+)~', function($m)use($pole,$delta){
        return dbQv($pole[ $m[1] + $delta ]);
      },$q)
    );
    */
    $o = parent::query($q);
    if($o === false) {
      var_dump($this->errorInfo());
      throw new Exception($this->errorInfo()[2]);
    }
    return $o;
  }

  /**
   * Quote identifier (with backticks)
   * @todo odladit jestli ten kód (v mysql) funguje
   */
  function qi($identifier) {
    return "`".str_replace("`","``",$identifier)."`";
  }

  /**
   * Quote value (with apostrophes around)
   */
  function qv($value) {
    return $this->quote($value);
  }

}
