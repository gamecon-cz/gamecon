<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\RolePermission
 */
class RolePermissionEntityStructure
{
    /**
     * @see RolePermission::$role
     */
    public const role = 'role';

    /**
     * @see RolePermission::$permission
     */
    public const permission = 'permission';
}
