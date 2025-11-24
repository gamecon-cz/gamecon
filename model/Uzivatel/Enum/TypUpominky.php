<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel\Enum;

/**
 * Typ upomínky dlužníků
 */
enum TypUpominky: string
{
    case TYDEN = 'tyden';
    case MESIC = 'mesic';
}
