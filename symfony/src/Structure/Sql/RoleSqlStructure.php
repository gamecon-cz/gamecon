<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\Role
 */
class RoleSqlStructure
{
    /**
     * @see Role
     */
    public const _table = 'role_seznam';

    /**
     * @see Role::$id
     */
    public const id_role = 'id_role';

    /**
     * @see Role::$kodRole
     */
    public const kod_role = 'kod_role';

    /**
     * @see Role::$nazevRole
     */
    public const nazev_role = 'nazev_role';

    /**
     * @see Role::$popisRole
     */
    public const popis_role = 'popis_role';

    /**
     * @see Role::$rocnikRole
     */
    public const rocnik_role = 'rocnik_role';

    /**
     * @see Role::$typRole
     */
    public const typ_role = 'typ_role';

    /**
     * @see Role::$vyznamRole
     */
    public const vyznam_role = 'vyznam_role';

    /**
     * @see Role::$skryta
     */
    public const skryta = 'skryta';

    /**
     * @see Role::$kategorieRole
     */
    public const kategorie_role = 'kategorie_role';
}
