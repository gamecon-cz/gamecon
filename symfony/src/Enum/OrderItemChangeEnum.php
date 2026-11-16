<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Values of shop_order_item_log.action: what happened to the purchase row.
 */
enum OrderItemChangeEnum: string
{
    case CREATED = 'created';

    case UPDATED = 'updated';

    /** Cancelled and corrected purchases alike; the cancellation archive says which. */
    case REMOVED = 'removed';
}
