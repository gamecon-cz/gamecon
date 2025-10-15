<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\UserRoleByYear
 */
class UserRoleByYearEntityStructure
{
    /**
     * @see UserRoleByYear::$id
     */
    public const id = 'id';

    /**
     * @see UserRoleByYear::$odKdy
     */
    public const odKdy = 'odKdy';

    /**
     * @see UserRoleByYear::$rocnik
     */
    public const rocnik = 'rocnik';

    /**
     * @see UserRoleByYear::$user
     */
    public const user = 'user';

    /**
     * @see UserRoleByYear::$role
     */
    public const role = 'role';
}
