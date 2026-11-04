<?php

declare(strict_types=1);

namespace Gamecon\Admin;

/**
 * The two looks of the admin. Both render the same module content, only the frame around it
 * (menu, user selection, footer) and the styling differ.
 */
enum VzhledAdminuEnum: string
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
     * The main template of the look, relative to the admin root.
     */
    public function getSablona(): string
    {
        return match ($this) {
            self::KLASICKY => 'templates/main.xtpl',
            self::MODERNI  => 'templates/moderni/main.xtpl',
        };
    }
}
