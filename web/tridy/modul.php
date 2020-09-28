<?php

/**
 * Modul stránek (controller). Objektové zapouzdření pro soubory ze složky
 * "moduly" v rootu stránek.
 */

class Modul {

  protected $src;
  protected $params = [];
  protected $vystup;
  protected $bezDekorace = false;
  protected $bezMenu = false;
  protected $bezStranky = false;
  protected $bezOkraju = false;
  protected $blackarrowStyl = false;
  protected $info;

  const VYCHOZI = 'titulka';

  /** Načte modul ze zadané cesty k souboru */
  protected function __construct($soubor) {
    $this->src = $soubor;
  }

  /** Jestli se má modul renderovat bez obalovacího divu (tj. ne jak stránka) */
  protected function bezDekorace($val = null) {
    if(isset($val)) $this->bezDekorace = (bool)$val;
    return $this->bezDekorace;
  }

  /** Jestli se modul má renderovat bez zobrazeného menu */
  function bezMenu($val = null) {
    if(isset($val)) $this->bezMenu = (bool)$val;
    return $this->bezMenu;
  }

  /** Jestli se má modul renderovat přes celou šířku monitoru */
  function bezOkraju($val = null) {
    if(isset($val)) $this->bezOkraju = (bool)$val;
    return $this->bezOkraju;
  }

  /** Jestli se má modul renderovat čistě jako plaintext */
  function bezStranky($val = null) {
    if(isset($val)) $this->bezStranky = $val;
    return $this->bezStranky;
  }

  /**
   * Jestli je modul v novém vizuálním stylu (codename blackarrow).
   * TODO po zmigrování všech modulů je možné toto postupně odstranit.
   */
  function blackarrowStyl($val = null) {
    if (isset($val)) $this->blackarrowStyl = $val;
    return $this->blackarrowStyl;
  }

  function info(Info $val = null): ?Info {
    if(isset($val)) $this->info = $val;
    return $this->info;
  }

  /** Název modulu (odpovídá části názvu souboru) */
  protected function nazev() {
    return preg_replace('@moduly/(.*)\.php@', '$1', $this->src);
  }

  /** Setter/getter pro parametr (proměnnou) předanou dovnitř modulu */
  function param($nazev) {
    if(func_num_args() == 2) $this->params[$nazev] = func_get_arg(1);
    else return @$this->params[$nazev];
  }

  /** Vrátí výchozí šablonu pro tento modul (pokud existuje) */
  protected function sablona() {
    if($this->blackarrowStyl) {
      $soubor = 'sablony/blackarrow/'.$this->nazev().'.xtpl';
    } else {
      $soubor = 'sablony/'.$this->nazev().'.xtpl';
    }

    if(is_file($soubor)) {
      return new XTemplate($soubor);
    } else {
      return null;
    }
  }

  /**
   * Vykoná kód modulu a nacacheuje výstup pro pozdější použití.
   * Viz, že modul dostává některé parametry pomocí proměnných resp. šablona se
   * načítá automaticky.
   */
  function spust() {
    extract($this->params); // TODO možná omezit explicitně parametry, které se smí extractnout, ať to není black magic
    $t = $this->sablona();
    ob_start();
    require $this->src;
    if($t) {
      $t->parse($this->nazev());
      $t->out($this->nazev());
    }
    $this->vystup = ob_get_clean();
    return $this;
  }

  /** Vrátí výstup, který modul vygeneroval */
  function vystup() {
    if($this->bezDekorace || $this->bezStranky)
      return $this->vystup;
    elseif($this->bezOkraju)
      return $this->vystup . '<style>.hlavni { max-width: 100%; }</style>';
    else
      return '<div class="blok stranka"><div class="obal">' . $this->vystup . '</div></div>';
  }

  /** Načte modul odpovídající dané Url (pokud není zadaná, použije aktuální) */
  static function zUrl(Url $url = null) {
    if(!$url) $url = Url::zAktualni()->cast(0);
    if(!$url) $url = self::VYCHOZI;
    return self::zNazvu($url);
  }

  /** Načte modul podle daného názvu */
  static function zNazvu($nazev) {
    $soubor = 'moduly/'.$nazev.'.php';
    if(is_file($soubor)) {
      return new self($soubor);
    } else {
      return null;
    }
  }

}
