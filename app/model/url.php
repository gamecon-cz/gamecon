<?php

/**
 * Třída pro zpracování url
 */

class Url
{

  protected $surova, $cista, $casti;
  protected static $aktualni;

  /**
   * Konstruktor bere na vstupu řetězec s názvem GET proměnné, z které zkusí
   * vycucnout URL
   */
  public function __construct($getName)
  {
    $this->surova = isset($_GET[$getName]) ? $_GET[$getName] : '';
    if(!self::povolena($this->surova))
      throw new UrlException('Nepovolené znaky v URL.');
    else
      $this->cista = $this->surova;
    $this->casti = explode('/',$this->cista);
  }

  /** Vrací část url na daném pořadím (od 0) */
  function cast($i)
  {
    if(isset($this->casti[$i])) return $this->casti[$i];
    else return null;
  }

  /** Vrací celou url */
  function cela()
  {
    return $this->cista;
  }

  /** Vrací počet zadaných částí url */
  function delka()
  {
    return $this->casti[0]?count($this->casti):0;
  }

  /** Řekne jestli jde o povolenou URL nebo ne */
  static function povolena($url) {
    return
      preg_match('@^[a-zA-Z0-9][A-Za-z0-9\-/\.]*$|^$@', $url) &&
      strpos($url, '/.') === false;
  }

  /**
   * Vrátí aktuální reálnou url
   * @todo zobecnit na $_SERVER nebo podobně
   */
  static function zAktualni() {
    if(!self::$aktualni) {
      self::$aktualni = new self('req');
    }
    return self::$aktualni;
  }

}

/**
 * Výjimky pro chyby v url
 */
class UrlException extends Exception {}
class UrlNotFoundException extends UrlException {}
