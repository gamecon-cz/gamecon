<?php

declare(strict_types=1);

namespace Gamecon\Kostymy;

/**
 * V jakém stavu se kostým vrátil. Poškozený kostým přepne do stavu „v opravě“.
 */
enum StavPriVraceniKostymuEnum: string
{
    case V_PORADKU = 'v_poradku';
    case POSKOZENY = 'poskozeny';

    public function getNazev(): string
    {
        return match ($this) {
            self::V_PORADKU => 'v pořádku',
            self::POSKOZENY => 'poškozený',
        };
    }
}
