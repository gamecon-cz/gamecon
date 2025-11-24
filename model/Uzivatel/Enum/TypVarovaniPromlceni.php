<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel\Enum;

/**
 * Typ varovného e-mailu o promlčení zůstatku
 */
enum TypVarovaniPromlceni: string
{
    case MESIC = 'mesic';
    case TYDEN = 'tyden';
}
