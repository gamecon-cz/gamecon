<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Export;

class ExportAktivitSloupce
{
  public const ID_AKTIVITY = 'ID aktivity';
  public const PROGRAMOVA_LINIE = 'Programová linie';
  public const NAZEV = 'Název';
  public const URL = 'URL';
  public const KRATKA_ANOTACE = 'Krátká anotace';
  public const TAGY = 'Tagy';
  public const DLOUHA_ANOTACE = 'Dlouhá anotace';
  public const DEN = 'Den';
  public const ZACATEK = 'Začátek';
  public const KONEC = 'Konec';
  public const MISTNOST = 'Místnost';
  public const VYPRAVECI = 'Vypravěči';
  public const KAPACITA_UNISEX = 'Kapacita unisex';
  public const KAPACITA_MUZI = 'Kapacita muži';
  public const KAPACITA_ZENY = 'Kapacita ženy';
  public const JE_TYMOVA = 'Je týmová';
  public const MINIMALNI_KAPACITA_TYMU = 'Minimální kapacita týmu';
  public const MAXIMALNI_KAPACITA_TYMU = 'Maximální kapacita týmu';
  public const CENA = 'Cena';
  public const BEZ_SLEV = 'Bez slev';
  public const VYBAVENI = 'Vybavení';
  public const STAV = 'Stav';

  public static function getVsechnySloupce(): array {
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
