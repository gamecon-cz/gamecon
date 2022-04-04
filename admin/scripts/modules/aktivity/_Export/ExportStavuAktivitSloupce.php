<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Export;

class ExportStavuAktivitSloupce
{
    public const NAZEV = 'Název';

    public static function vsechnySloupceStavu(): array {
        return [
            self::NAZEV,
        ];
    }
}
