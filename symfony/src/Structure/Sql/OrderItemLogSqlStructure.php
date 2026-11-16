<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\OrderItemLog
 */
class OrderItemLogSqlStructure
{
    public const _table = 'shop_order_item_log';

    /**
     * @see OrderItemLog::$id
     */
    public const id = 'id';

    /**
     * @see OrderItemLog::$orderItemId
     */
    public const order_item_id = 'order_item_id';

    /**
     * @see OrderItemLog::$customerId
     */
    public const customer_id = 'customer_id';

    /**
     * @see OrderItemLog::$year
     */
    public const year = 'year';

    /**
     * @see OrderItemLog::$action
     */
    public const action = 'action';

    /**
     * @see OrderItemLog::$changes
     */
    public const changes = 'changes';

    /**
     * @see OrderItemLog::$changedById
     */
    public const changed_by_id = 'changed_by_id';

    /**
     * @see OrderItemLog::$origin
     */
    public const origin = 'origin';

    /**
     * @see OrderItemLog::$changedAt
     */
    public const changed_at = 'changed_at';
}
