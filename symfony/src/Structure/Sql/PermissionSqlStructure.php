<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\Permission
 */
class PermissionSqlStructure
{
    /**
     * @see Permission
     */
    public const _table = 'r_prava_soupis';

    /**
     * @see Permission::$id
     */
    public const id_prava = 'id_prava';

    /**
     * @see Permission::$jmenoPrava
     */
    public const jmeno_prava = 'jmeno_prava';

    /**
     * @see Permission::$popisPrava
     */
    public const popis_prava = 'popis_prava';
}
