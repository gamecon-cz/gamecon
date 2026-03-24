<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\ProductBundle
 */
class ProductBundleEntityStructure
{
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
    public const applicableToRoles = 'applicableToRoles';

    /**
     * @see ProductBundle::$createdAt
     */
    public const createdAt = 'createdAt';

    /**
     * @see ProductBundle::$updatedAt
     */
    public const updatedAt = 'updatedAt';

    /**
     * @see ProductBundle::$products
     */
    public const products = 'products';
}
