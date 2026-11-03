<?php

declare(strict_types=1);

namespace Gamecon\Web;

/**
 * Jak se obrázek vkládá do textů stránek, novinek a blogu.
 *
 * @see \Stranka::obrazek() HTML zápis rozpozná jen jako `<img src="…"…>`, proto ho nabízíme všude stejný.
 */
class ZapisObrazkuVTextu
{
    public static function markdown(string $cestaVTextu): string
    {
        return '![](' . $cestaVTextu . ')';
    }

    public static function html(string $cestaVTextu): string
    {
        return '<img src="' . $cestaVTextu . '" alt="">';
    }
}
