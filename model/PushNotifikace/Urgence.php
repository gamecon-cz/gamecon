<?php

declare(strict_types=1);

namespace Gamecon\PushNotifikace;

/**
 * Naléhavost zprávy pro push službu (hlavička Urgency, RFC 8030). Podle ní služba a telefon rozhodují, jestli kvůli
 * zprávě probudí zařízení v úsporném režimu, nebo počkají.
 */
enum Urgence: string
{
    case VELMI_NIZKA = 'very-low';
    case NIZKA = 'low';
    case NORMALNI = 'normal';
    case VYSOKA = 'high';

    public function getNazev(): string
    {
        return match ($this) {
            self::VELMI_NIZKA => 'velmi nízká',
            self::NIZKA       => 'nízká',
            self::NORMALNI    => 'normální',
            self::VYSOKA      => 'vysoká',
        };
    }
}
