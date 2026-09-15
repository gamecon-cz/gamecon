<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\ProductBundle
 */
class ProductBundleSqlStructure
{
    public const _table = 'product_bundle';

    /**
     * @see ProductBundle::$id
     */
    public const id = 'id';

    /**
     * @see ProductBundle::$name
     */
    public const name = 'name';

    /**
     * @see ProductBundle::$forced
     */
    public const forced = 'forced';

    /**
     * @see ProductBundle::$applicableToRoles
     */
    public const applicable_to_roles = 'applicable_to_roles';

    /**
     * @see ProductBundle::$createdAt
     */
    public const created_at = 'created_at';

    /**
     * @see ProductBundle::$updatedAt
     */
    public const updated_at = 'updated_at';
}
