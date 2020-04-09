<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Export;

class ExportVypravecuSloupce
{
  public const ID_UZIVATELE = 'ID uživatele';
  public const EMAIL = 'Email';
  public const PREZDIVKA = 'Přezdívka';

  public static function vsechnySloupce(): array {
    return [
      self::ID_UZIVATELE,
      self::EMAIL,
      self::PREZDIVKA,
    ];
  }
}
