<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\ProductVariant
 */
class ProductVariantSqlStructure
{
    public const _table = 'product_variant';

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
    public const remaining_quantity = 'remaining_quantity';

    /**
     * @see ProductVariant::$reservedForOrganizers
     */
    public const reserved_for_organizers = 'reserved_for_organizers';

    /**
     * @see ProductVariant::$accommodationDay
     */
    public const accommodation_day = 'accommodation_day';

    /**
     * @see ProductVariant::$position
     */
    public const position = 'position';

    /**
     * @see ProductVariant::$product
     */
    public const product_id = 'product_id';
}
