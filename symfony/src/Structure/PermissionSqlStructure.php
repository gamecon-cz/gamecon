<?php

declare(strict_types=1);

namespace App\Structure;

/**
 * Structure for @see \App\Entity\Permission
 * SQL table `r_prava_soupis`
 */
class PermissionSqlStructure
{
    public const ID = 'id_prava';
    public const JMENO_PRAVA = 'jmeno_prava';
    public const POPIS_PRAVA = 'popis_prava';
}
