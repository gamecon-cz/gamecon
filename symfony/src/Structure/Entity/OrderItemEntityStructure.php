<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\OrderItem
 */
class OrderItemEntityStructure
{
    /**
     * @see OrderItem::$id
     */
    public const id = 'id';

    /**
     * @see OrderItem::$year
     */
    public const year = 'year';

    /**
     * @see OrderItem::$productName
     */
    public const productName = 'productName';

    /**
     * @see OrderItem::$productCode
     */
    public const productCode = 'productCode';

    /**
     * @see OrderItem::$productDescription
     */
    public const productDescription = 'productDescription';

    /**
     * @see OrderItem::$productTags
     */
    public const productTags = 'productTags';

    /**
     * @see OrderItem::$purchasePrice
     */
    public const purchasePrice = 'purchasePrice';

    /**
     * @see OrderItem::$originalPrice
     */
    public const originalPrice = 'originalPrice';

    /**
     * @see OrderItem::$discountAmount
     */
    public const discountAmount = 'discountAmount';

    /**
     * @see OrderItem::$discountReason
     */
    public const discountReason = 'discountReason';

    /**
     * @see OrderItem::$purchasedAt
     */
    public const purchasedAt = 'purchasedAt';

    /**
     * @see OrderItem::$customer
     */
    public const customer = 'customer';

    /**
     * @see OrderItem::$orderer
     */
    public const orderer = 'orderer';

    /**
     * @see OrderItem::$order
     */
    public const order = 'order';

    /**
     * @see OrderItem::$product
     */
    public const product = 'product';
}
