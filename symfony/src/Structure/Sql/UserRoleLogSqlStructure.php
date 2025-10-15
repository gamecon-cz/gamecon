<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\UserRoleLog
 */
class UserRoleLogSqlStructure
{
    /**
     * @see UserRoleLog
     */
    public const _table = 'uzivatele_role_log';

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
    public const id_uzivatele = 'id_uzivatele';

    /**
     * @see UserRoleLog::$role
     */
    public const id_role = 'id_role';

    /**
     * @see UserRoleLog::$changedBy
     */
    public const id_zmenil = 'id_zmenil';
}
