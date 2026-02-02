<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\CancelledOrderItem
 *
 * Maps legacy camelCase property names to entity properties
 */
class CancelledOrderItemEntityStructure
{
    /**
     * @see CancelledOrderItem::$id
     */
    public const idNakupu = 'id';

    /**
     * @see CancelledOrderItem::$customer
     */
    public const idUzivatele = 'customer';

    /**
     * @see CancelledOrderItem::$product
     */
    public const idPredmetu = 'product';

    /**
     * @see CancelledOrderItem::$year
     */
    public const rocnik = 'year';

    /**
     * @see CancelledOrderItem::$purchasePrice
     */
    public const cenaNakupni = 'purchasePrice';

    /**
     * @see CancelledOrderItem::$purchasedAt
     */
    public const datumNakupu = 'purchasedAt';

    /**
     * @see CancelledOrderItem::$cancelledAt
     */
    public const datumZruseni = 'cancelledAt';

    /**
     * @see CancelledOrderItem::$cancellationReason
     */
    public const zdrojZruseni = 'cancellationReason';
}
