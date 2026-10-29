<?php

declare(strict_types=1);

namespace Gamecon\Web\Motiv;

/**
 * Special themes of the modern look (April Fools' and the like). A theme is a stylesheet written in code, it can do
 * anything CSS can, unlike the colours of the year; the admin only plans when it is on.
 * A new theme is a new case here and web/soubory/moderni/motivy/<value>.css.
 */
enum MotivWebuEnum: string
{
    case APRIL = 'april';

    public function getNazev(): string
    {
        return match ($this) {
            self::APRIL => 'Apríl',
        };
    }

    public function getPopis(): string
    {
        return match ($this) {
            self::APRIL => 'Comic Sans, logo vzhůru nohama, karty nakřivo a růžová místo červené.',
        };
    }

    /**
     * Relative to web/, like the other stylesheets of the look.
     */
    public function getCssSoubor(): string
    {
        return 'soubory/moderni/motivy/' . $this->value . '.css';
    }
}
