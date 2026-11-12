<?php

declare(strict_types=1);

namespace Gamecon\Kostymy;

/**
 * Rezervovat jde jen kostým k dispozici. Kostým v opravě nebo vyřazený nechá stávající rezervace být,
 * správce je uvidí na stránce Konflikty.
 */
enum StavKostymuEnum: string
{
    case K_DISPOZICI = 'k_dispozici';
    case V_OPRAVE = 'v_oprave';
    case VYRAZENY = 'vyrazeny';

    public function getNazev(): string
    {
        return match ($this) {
            self::K_DISPOZICI => 'k dispozici',
            self::V_OPRAVE    => 'v opravě',
            self::VYRAZENY    => 'vyřazený',
        };
    }

    public function isRezervovatelny(): bool
    {
        return $this === self::K_DISPOZICI;
    }
}
