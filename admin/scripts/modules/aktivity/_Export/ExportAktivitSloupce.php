<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Export;

class ExportAktivitSloupce
{
  public const ID_AKTIVITY = 'ID aktivity'; // id_akce
  public const PROGRAMOVA_LINIE = 'Programová linie'; // typ
  public const NAZEV = 'Název'; // nazev_akce
  public const URL = 'URL'; // url_akce
  public const KRATKA_ANOTACE = 'Krátká anotace'; // popis_kratky
  public const TAGY = 'Tagy';
  public const DLOUHA_ANOTACE = 'Dlouhá anotace'; // popis
  public const DEN = 'Den'; // zacatek / konec
  public const ZACATEK = 'Začátek'; // zacatek
  public const KONEC = 'Konec'; // konec
  public const MISTNOST = 'Místnost'; // lokace
  public const VYPRAVECI = 'Vypravěči';
  public const KAPACITA_UNISEX = 'Kapacita unisex'; // kapacita
  public const KAPACITA_MUZI = 'Kapacita muži'; // kapacita_m
  public const KAPACITA_ZENY = 'Kapacita ženy'; // kapacita_f
  public const JE_TYMOVA = 'Je týmová'; // teamova
  public const MINIMALNI_KAPACITA_TYMU = 'Minimální kapacita týmu'; // team_min
  public const MAXIMALNI_KAPACITA_TYMU = 'Maximální kapacita týmu'; // team_max
  public const CENA = 'Cena'; // cena
  public const BEZ_SLEV = 'Bez slev'; // bez_slevy
  public const VYBAVENI = 'Vybavení'; // vybaveni
  public const STAV = 'Stav'; // stav

  public static function vsechnySloupce(): array {
    return [
      self::ID_AKTIVITY,
      self::PROGRAMOVA_LINIE,
      self::NAZEV,
      self::URL,
      self::KRATKA_ANOTACE,
      self::TAGY,
      self::DLOUHA_ANOTACE,
      self::DEN,
      self::ZACATEK,
      self::KONEC,
      self::MISTNOST,
      self::VYPRAVECI,
      self::KAPACITA_UNISEX,
      self::KAPACITA_MUZI,
      self::KAPACITA_ZENY,
      self::JE_TYMOVA,
      self::MINIMALNI_KAPACITA_TYMU,
      self::MAXIMALNI_KAPACITA_TYMU,
      self::CENA,
      self::BEZ_SLEV,
      self::VYBAVENI,
      self::STAV,
    ];
  }
}
