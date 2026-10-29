<?php

declare(strict_types=1);

namespace Gamecon\Web\OznameniNaWebu;

/**
 * How pressing an announcement is: it decides its colour, its place among the others and whether a visitor may close it.
 */
enum TypOznameniNaWebuEnum: string
{
    case INFO = 'info';
    case UPOZORNENI = 'upozorneni';
    case NALEHAVE = 'nalehave';

    public function getNazev(): string
    {
        return match ($this) {
            self::INFO       => 'informace',
            self::UPOZORNENI => 'upozornění',
            self::NALEHAVE   => 'naléhavé',
        };
    }

    public function getPopis(): string
    {
        return match ($this) {
            self::INFO       => 'Běžná zpráva, návštěvník ji může zavřít.',
            self::UPOZORNENI => 'Změna, o které je dobré vědět (přesun, zpoždění), návštěvník ji může zavřít.',
            self::NALEHAVE   => 'Bezpečnost nebo zrušení, nejde zavřít.',
        };
    }

    /**
     * Visitors must not miss an urgent announcement, so it cannot be closed.
     */
    public function lzeSkryt(): bool
    {
        return $this !== self::NALEHAVE;
    }

    /**
     * The more pressing announcement goes first.
     */
    public function getPoradi(): int
    {
        return match ($this) {
            self::NALEHAVE   => 1,
            self::UPOZORNENI => 2,
            self::INFO       => 3,
        };
    }
}
