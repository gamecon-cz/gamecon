<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel\Enum;

/**
 * Jak se dlužník letos zúčastnil GameConu - určuje, jakým textem se dá upomenout.
 *
 * Upomínka chodí všem dlužníkům bez ohledu na letošní účast, takže text nesmí
 * tvrdit něco, co pro příjemce neplatí: dluh z minulých let může mít i ten,
 * kdo letos vůbec nepřijel.
 */
enum UcastNaGc
{
    case PRITOMEN;
    case JEN_PRIHLASEN;
    case NEDORAZIL;
}
