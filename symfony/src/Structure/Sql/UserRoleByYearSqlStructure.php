<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\UserRoleByYear
 */
class UserRoleByYearSqlStructure
{
    /**
     * @see UserRoleByYear
     */
    public const _table = 'uzivatele_role_podle_rocniku';

    /**
     * @see UserRoleByYear::$id
     */
    public const id = 'id';

    /**
     * @see UserRoleByYear::$odKdy
     */
    public const od_kdy = 'od_kdy';

    /**
     * @see UserRoleByYear::$rocnik
     */
    public const rocnik = 'rocnik';

    /**
     * @see UserRoleByYear::$user
     */
    public const id_uzivatele = 'id_uzivatele';

    /**
     * @see UserRoleByYear::$role
     */
    public const id_role = 'id_role';
}
