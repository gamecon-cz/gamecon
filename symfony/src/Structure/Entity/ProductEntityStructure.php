<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\Product
 */
class ProductEntityStructure
{
    /**
     * @see Product::$id
     */
    public const id = 'id';

    /**
     * @see Product::$name
     */
    public const name = 'name';

    /**
     * @see Product::$code
     */
    public const code = 'code';

    /**
     * @see Product::$currentPrice
     */
    public const currentPrice = 'currentPrice';

    /**
     * @see Product::$state
     */
    public const state = 'state';

    /**
     * @see Product::$availableUntil
     */
    public const availableUntil = 'availableUntil';

    /**
     * @see Product::$producedQuantity
     */
    public const producedQuantity = 'producedQuantity';

    /**
     * @see Product::$accommodationDay
     */
    public const accommodationDay = 'accommodationDay';

    /**
     * @see Product::$description
     */
    public const description = 'description';

    /**
     * @see Product::$archivedAt
     */
    public const archivedAt = 'archivedAt';

    /**
     * @see Product::$reservedForOrganizers
     */
    public const reservedForOrganizers = 'reservedForOrganizers';

    /**
     * @see Product::$tags
     */
    public const tags = 'tags';

    /**
     * @see Product::$variants
     */
    public const variants = 'variants';

    /**
     * @see Product::$bundles
     */
    public const bundles = 'bundles';

    /**
     * @see Product::$discounts
     */
    public const discounts = 'discounts';

    /**
     * @see Product::$orderItems
     */
    public const orderItems = 'orderItems';

    /**
     * @see Product::$cancelledOrderItems
     */
    public const cancelledOrderItems = 'cancelledOrderItems';
}
