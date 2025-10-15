<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\UserRoleText
 */
class UserRoleTextSqlStructure
{
    /**
     * @see UserRoleText
     */
    public const _table = 'role_texty_podle_uzivatele';

    /**
     * @see UserRoleText::$id
     */
    public const id = 'id';

    /**
     * @see UserRoleText::$vyznamRole
     */
    public const vyznam_role = 'vyznam_role';

    /**
     * @see UserRoleText::$popisRole
     */
    public const popis_role = 'popis_role';

    /**
     * @see UserRoleText::$user
     */
    public const id_uzivatele = 'id_uzivatele';
}
