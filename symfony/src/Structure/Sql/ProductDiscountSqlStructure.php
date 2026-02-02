<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\ProductDiscount
 */
class ProductDiscountSqlStructure
{
    public const _table = 'product_discount';

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
    public const discount_percent = 'discount_percent';

    /**
     * @see ProductDiscount::$maxQuantity
     */
    public const max_quantity = 'max_quantity';

    /**
     * @see ProductDiscount::$createdAt
     */
    public const created_at = 'created_at';

    /**
     * @see ProductDiscount::$updatedAt
     */
    public const updated_at = 'updated_at';

    /**
     * @see ProductDiscount::$product
     */
    public const product_id = 'product_id';
}
