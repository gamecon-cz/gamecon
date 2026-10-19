<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Values of role_seznam.typ_role.
 *
 * Supersedes Gamecon\Role\Role::TYP_*.
 */
enum RoleTypeEnum: string
{
    /** Valid only in the year of its rocnik_role. */
    case YEARLY = 'rocnikova';

    /** Taking part in a year (registered, present, left); stays valid in later years, so it can still be read. */
    case PARTICIPATION = 'ucast';

    /** Has no year (rocnik_role -1) and stays valid. */
    case PERMANENT = 'trvala';
}
