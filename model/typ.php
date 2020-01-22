<?php

/**
 * Typ aktivit (programová linie)
 */
class Typ extends DbObject {

  protected static $tabulka = 'akce_typy';
  protected static $pk = 'id_typu';

  const LKD = 8;
  const DRD = 9;
  const TECHNICKA = 10;

  /** Vrátí popisek bez html a názvu */
  function bezNazvu() {
    return trim(strip_tags(preg_replace(
      '@<h1>[^<]+</h1>@',
      '',
      $this->oTypu(),
      1 // limit
    )));
  }

  function nazev() {
    return $this->r['typ_1pmn'];
  }

  /** Název natáhnutý ze stránky */
  function nazevDlouhy() {
    preg_match('@<h1>([^<]+)</h1>@', $this->oTypu(), $m);
    return $m[1];
  }

  function oTypu() {
    $s = Stranka::zId($this->r['stranka_o']);
    return $s ? $s->html() : null;
  }

  function poradi() {
    return $this->r['poradi'];
  }

  function posilatMailyNedorazivsim() {
    return (bool) $this->r['mail_neucast'];
  }

  function url(): string {
    return $this->r['url_typu_mn'];
  }

  static function zUrl($url = null): ?Typ {
    if($url === null) $url = Url::zAktualni()->cela();
    return self::zWhereRadek('url_typu_mn = $1', [$url]);
  }

  static function zViditelnych() {
    return self::zWhere('poradi > 0');
  }

}
