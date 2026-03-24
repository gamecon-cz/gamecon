<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\Order
 */
class OrderEntityStructure
{
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
    public const totalPrice = 'totalPrice';

    /**
     * @see Order::$createdAt
     */
    public const createdAt = 'createdAt';

    /**
     * @see Order::$completedAt
     */
    public const completedAt = 'completedAt';

    /**
     * @see Order::$customer
     */
    public const customer = 'customer';

    /**
     * @see Order::$items
     */
    public const items = 'items';
}
