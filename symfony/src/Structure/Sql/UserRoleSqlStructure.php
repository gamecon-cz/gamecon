<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\UserRole
 */
class UserRoleSqlStructure
{
    /**
     * @see UserRole
     */
    public const _table = 'uzivatele_role';

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
    public const id_uzivatele = 'id_uzivatele';

    /**
     * @see UserRole::$role
     */
    public const id_role = 'id_role';

    /**
     * @see UserRole::$givenBy
     */
    public const posadil = 'posadil';
}
