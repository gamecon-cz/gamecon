<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\RolePermission
 */
class RolePermissionSqlStructure
{
    /**
     * @see RolePermission
     */
    public const _table = 'prava_role';

    /**
     * @see RolePermission::$role
     */
    public const id_role = 'id_role';

    /**
     * @see RolePermission::$permission
     */
    public const id_prava = 'id_prava';
}
