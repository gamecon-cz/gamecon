<?php

/**
 * Náhled / prohlížeč obrázku s cacheováním atd..
 */

class Nahled {

  protected $s = null;
  protected $v = null;
  protected $mod = null;
  protected $soubor;
  protected $datum;       // poslední změna orig. souboru

  const PASUJ = 1;

  protected function __construct($soubor) {
    $this->soubor = $soubor;
    $this->datum  = @filemtime($this->soubor);
    if($this->datum === false) throw new Exception('Obrázek neexistuje');
  }

  /** Vrátí url obrázku, je možné ji cacheovat navždy */
  function __toString() {
    $hash = md5($this->soubor . $this->mod . $this->v . $this->s);
    $cache = CACHE . '/img/' . $hash . '.jpg';
    $url = URL_CACHE . '/img/' . $hash . '.jpg?m=' . $this->datum;
    if(@filemtime($cache) < $this->datum) $this->uloz($cache);
    return $url;
  }

  /** Zmenší obrázek aby pasoval do obdelníku s šířkou $s a výškou $v */
  function pasuj($s, $v = null) {
    $this->mod = self::PASUJ;
    $this->s = $s;
    $this->v = $v;
    return $this;
  }

  /** Uloží stávající soubor s požadovanými úpravami */
  protected function uloz($cil) {
    $o = Obrazek::zSouboru($this->soubor);
    $s = $this->s ?: 10000;
    $v = $this->v ?: 10000;
    if($this->mod == self::PASUJ) $o->fitCrop($s, $v);
    $o->uloz($cil, 92);
  }

  static function zSouboru($nazev) {
    return new self($nazev);
  }

}
