<?php

declare(strict_types=1);

namespace Gamecon\Kostymy;

enum StrihKostymuEnum: string
{
    case PANSKY = 'pansky';
    case DAMSKY = 'damsky';
    case UNISEX = 'unisex';

    public function getNazev(): string
    {
        return match ($this) {
            self::PANSKY => 'pánský',
            self::DAMSKY => 'dámský',
            self::UNISEX => 'unisex',
        };
    }
}
