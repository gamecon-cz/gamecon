<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\UserRoleLog
 */
class UserRoleLogEntityStructure
{
    /**
     * @see UserRoleLog::$id
     */
    public const id = 'id';

    /**
     * @see UserRoleLog::$zmena
     */
    public const zmena = 'zmena';

    /**
     * @see UserRoleLog::$kdy
     */
    public const kdy = 'kdy';

    /**
     * @see UserRoleLog::$user
     */
    public const user = 'user';

    /**
     * @see UserRoleLog::$role
     */
    public const role = 'role';

    /**
     * @see UserRoleLog::$changedBy
     */
    public const changedBy = 'changedBy';
}
