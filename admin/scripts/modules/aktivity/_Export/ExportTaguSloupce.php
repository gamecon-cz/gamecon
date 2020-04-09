<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Export;

class ExportTaguSloupce
{
  public const ID_TAGU = 'ID tagu';
  public const KATEGORIE_TAGU = 'Kategorie tagu';
  public const NÁZEV_TAGU = 'Název tagu';
  public const POZNAMKA = 'Poznámka';

  public static function vsechnySloupce(): array {
    return [
      self::ID_TAGU,
      self::KATEGORIE_TAGU,
      self::NÁZEV_TAGU,
      self::POZNAMKA,
    ];
  }
}
