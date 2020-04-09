<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Export;

class ExportTaguSloupce
{
  public const ID_TAGU = 'ID tagu';
  public const NAZEV_TAGU = 'Název tagu';
  public const POZNAMKA = 'Poznámka';
  public const KATEGORIE_TAGU = 'Kategorie tagu';

  public static function vsechnySloupce(): array {
    return [
      self::ID_TAGU,
      self::NAZEV_TAGU,
      self::POZNAMKA,
      self::KATEGORIE_TAGU,
    ];
  }
}
