<?php

declare(strict_types=1);

namespace Gamecon\Web;

/**
 * The two looks of the public web. Both run the same module code, a module with its own template
 * in the modern look (sablony/moderni/<modul>.xtpl) shows it, any other keeps its classic content
 * inside the modern frame.
 */
enum VzhledWebuEnum: string
{
    case KLASICKY = 'klasicky';
    case MODERNI = 'moderni';

    public function getNazev(): string
    {
        return match ($this) {
            self::KLASICKY => 'klasický',
            self::MODERNI  => 'moderní',
        };
    }

    /**
     * The directory with the templates of the look, relative to web/sablony.
     */
    public function getAdresarSablon(): string
    {
        return match ($this) {
            self::KLASICKY => 'blackarrow',
            self::MODERNI  => 'moderni',
        };
    }
}
