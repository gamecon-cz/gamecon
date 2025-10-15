<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\UserRole
 */
class UserRoleEntityStructure
{
    /**
     * @see UserRole::$id
     */
    public const id = 'id';

    /**
     * @see UserRole::$posazen
     */
    public const posazen = 'posazen';

    /**
     * @see UserRole::$user
     */
    public const user = 'user';

    /**
     * @see UserRole::$role
     */
    public const role = 'role';

    /**
     * @see UserRole::$givenBy
     */
    public const givenBy = 'givenBy';
}
