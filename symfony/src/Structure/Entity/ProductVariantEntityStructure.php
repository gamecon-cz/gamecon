<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\ProductVariant
 */
class ProductVariantEntityStructure
{
    /**
     * @see ProductVariant::$id
     */
    public const id = 'id';

    /**
     * @see ProductVariant::$name
     */
    public const name = 'name';

    /**
     * @see ProductVariant::$code
     */
    public const code = 'code';

    /**
     * @see ProductVariant::$price
     */
    public const price = 'price';

    /**
     * @see ProductVariant::$remainingQuantity
     */
    public const remainingQuantity = 'remainingQuantity';

    /**
     * @see ProductVariant::$reservedForOrganizers
     */
    public const reservedForOrganizers = 'reservedForOrganizers';

    /**
     * @see ProductVariant::$accommodationDay
     */
    public const accommodationDay = 'accommodationDay';

    /**
     * @see ProductVariant::$position
     */
    public const position = 'position';

    /**
     * @see ProductVariant::$product
     */
    public const product = 'product';

    /**
     * @see ProductVariant::$orderItems
     */
    public const orderItems = 'orderItems';

    /**
     * @see ProductVariant::$bundles
     */
    public const bundles = 'bundles';
}
