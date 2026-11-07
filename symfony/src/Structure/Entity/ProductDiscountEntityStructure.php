<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\ProductDiscount
 */
class ProductDiscountEntityStructure
{
    /**
     * @see ProductDiscount::$id
     */
    public const id = 'id';

    /**
     * @see ProductDiscount::$role
     */
    public const role = 'role';

    /**
     * @see ProductDiscount::$discountPercent
     */
    public const discountPercent = 'discountPercent';

    /**
     * @see ProductDiscount::$maxQuantity
     */
    public const maxQuantity = 'maxQuantity';

    /**
     * @see ProductDiscount::$createdAt
     */
    public const createdAt = 'createdAt';

    /**
     * @see ProductDiscount::$updatedAt
     */
    public const updatedAt = 'updatedAt';

    /**
     * @see ProductDiscount::$product
     */
    public const product = 'product';
}
