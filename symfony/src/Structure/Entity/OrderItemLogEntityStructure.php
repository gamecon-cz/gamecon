<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\OrderItemLog
 */
class OrderItemLogEntityStructure
{
    /**
     * @see OrderItemLog::$id
     */
    public const id = 'id';

    /**
     * @see OrderItemLog::$orderItemId
     */
    public const orderItemId = 'orderItemId';

    /**
     * @see OrderItemLog::$customerId
     */
    public const customerId = 'customerId';

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
    public const changedById = 'changedById';

    /**
     * @see OrderItemLog::$origin
     */
    public const origin = 'origin';

    /**
     * @see OrderItemLog::$changedAt
     */
    public const changedAt = 'changedAt';
}
