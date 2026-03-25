<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Type of role change (matches legacy Uzivatel::POSAZEN/SESAZEN constants).
 *
 * Stored in `uzivatele_role_log.zmena` column.
 */
enum RoleChangeType: string
{
    case ASSIGNED = 'posazen';
    case REMOVED = 'sesazen';
}
