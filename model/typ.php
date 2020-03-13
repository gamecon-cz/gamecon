<?php

/**
 * Typ aktivit (programová linie)
 * @method static Typ|null zId($id)
 * @method static Typ[] zVsech()
 */
class Typ extends DbObject
{

  public static function zNazvu(string $nazev): ?Typ {
    return static::zWhereRadek(static::$sloupecNazev . ' = ' . dbQv($nazev));
  }

  protected static $tabulka = 'akce_typy';
  protected static $pk = 'id_typu';
  protected static $sloupecNazev = 'typ_1pmn';

  const TURNAJ_V_DESKOVKACH = 1;
  const LARP = 2;
  const PREDNASKA = 3;
  const RPG = 4;
  const WORKSHOP = 5;
  const WARGAMING = 6;
  const BONUS = 7;
  const LKD = 8; // legendy klubu dobrodruhů
  const DRD = 9; // mistrovství v DrD
  const TECHNICKA = 10;
  const EPIC = 11;
  const DOPROVODNY_PROGRAM = 12;
  const DESKOHERNA = 13;

  public function id(): int {
    return (int)parent::id();
  }

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

  public function __toString() {
    return (string)$this->nazev();
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
    return (bool)$this->r['mail_neucast'];
  }

  function url(): string {
    return $this->r['url_typu_mn'];
  }

  static function zUrl($url = null): ?Typ {
    if ($url === null) $url = Url::zAktualni()->cela();
    return self::zWhereRadek('url_typu_mn = $1', [$url]);
  }

  static function zViditelnych() {
    return self::zWhere('poradi > 0');
  }

}
