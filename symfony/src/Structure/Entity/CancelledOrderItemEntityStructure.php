<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\CancelledOrderItem
 */
class CancelledOrderItemEntityStructure
{
    /**
     * @see CancelledOrderItem::$id
     */
    public const id = 'id';

    /**
     * @see CancelledOrderItem::$year
     */
    public const year = 'year';

    /**
     * @see CancelledOrderItem::$purchasePrice
     */
    public const purchasePrice = 'purchasePrice';

    /**
     * @see CancelledOrderItem::$purchasedAt
     */
    public const purchasedAt = 'purchasedAt';

    /**
     * @see CancelledOrderItem::$cancelledAt
     */
    public const cancelledAt = 'cancelledAt';

    /**
     * @see CancelledOrderItem::$cancellationReason
     */
    public const cancellationReason = 'cancellationReason';

    /**
     * @see CancelledOrderItem::$customer
     */
    public const customer = 'customer';

    /**
     * @see CancelledOrderItem::$product
     */
    public const product = 'product';
}
