<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\Permission
 */
class PermissionEntityStructure
{
    /**
     * @see Permission::$id
     */
    public const id = 'id';

    /**
     * @see Permission::$jmenoPrava
     */
    public const jmenoPrava = 'jmenoPrava';

    /**
     * @see Permission::$popisPrava
     */
    public const popisPrava = 'popisPrava';
}
