<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Export;

class ExportLokaciSloupce
{
  public const ID_MISTNOSTI = 'ID místnosti';
  public const NAZEV = 'Název';
  public const DVERE = 'Dveře';
  public const POZNAMKA = 'Poznámka';

  public static function vsechnySloupce(): array {
    return [
      self::ID_MISTNOSTI,
      self::NAZEV,
      self::DVERE,
      self::POZNAMKA,
    ];
  }
}
