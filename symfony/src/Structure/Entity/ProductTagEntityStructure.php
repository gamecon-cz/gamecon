<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\ProductTag
 */
class ProductTagEntityStructure
{
    /**
     * @see ProductTag::$id
     */
    public const id = 'id';

    /**
     * @see ProductTag::$code
     */
    public const code = 'code';

    /**
     * @see ProductTag::$name
     */
    public const name = 'name';

    /**
     * @see ProductTag::$description
     */
    public const description = 'description';

    /**
     * @see ProductTag::$createdAt
     */
    public const createdAt = 'createdAt';

    /**
     * @see ProductTag::$updatedAt
     */
    public const updatedAt = 'updatedAt';

    /**
     * @see ProductTag::$products
     */
    public const products = 'products';
}
