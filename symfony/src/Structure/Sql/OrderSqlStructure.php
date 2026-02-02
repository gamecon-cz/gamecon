<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\Order
 */
class OrderSqlStructure
{
    public const _table = 'shop_order';

    /**
     * @see Order::$id
     */
    public const id = 'id';

    /**
     * @see Order::$year
     */
    public const year = 'year';

    /**
     * @see Order::$status
     */
    public const status = 'status';

    /**
     * @see Order::$totalPrice
     */
    public const total_price = 'total_price';

    /**
     * @see Order::$createdAt
     */
    public const created_at = 'created_at';

    /**
     * @see Order::$completedAt
     */
    public const completed_at = 'completed_at';

    /**
     * @see Order::$customer
     */
    public const customer_id = 'customer_id';
}
