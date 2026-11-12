<?php

declare(strict_types=1);

namespace Gamecon\Kostymy;

/**
 * Velikost kostýmu z pevného seznamu, aby šla filtrovat. Podrobnosti patří do měr (`kostym.miry`).
 * Pořadí případů je pořadí ve filtru a ve formuláři.
 */
enum VelikostKostymuEnum: string
{
    case XS = 'xs';
    case S = 's';
    case M = 'm';
    case L = 'l';
    case XL = 'xl';
    case XXL = 'xxl';
    case XXXL = 'xxxl';
    case UNIVERZALNI = 'univerzalni';

    public function getNazev(): string
    {
        return match ($this) {
            self::UNIVERZALNI => 'univerzální',
            default           => $this->name,
        };
    }
}
