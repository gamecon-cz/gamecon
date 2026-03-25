<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\OrderItem
 */
class OrderItemSqlStructure
{
    public const _table = 'shop_nakupy';

    /**
     * @see OrderItem::$id
     */
    public const id_nakupu = 'id_nakupu';

    /**
     * @see OrderItem::$year
     */
    public const rok = 'rok';

    /**
     * @see OrderItem::$productName
     */
    public const product_name = 'product_name';

    /**
     * @see OrderItem::$productCode
     */
    public const product_code = 'product_code';

    /**
     * @see OrderItem::$productDescription
     */
    public const product_description = 'product_description';

    /**
     * @see OrderItem::$productTags
     */
    public const product_tags = 'product_tags';

    /**
     * @see OrderItem::$variantName
     */
    public const variant_name = 'variant_name';

    /**
     * @see OrderItem::$variantCode
     */
    public const variant_code = 'variant_code';

    /**
     * @see OrderItem::$purchasePrice
     */
    public const cena_nakupni = 'cena_nakupni';

    /**
     * @see OrderItem::$originalPrice
     */
    public const original_price = 'original_price';

    /**
     * @see OrderItem::$discountAmount
     */
    public const discount_amount = 'discount_amount';

    /**
     * @see OrderItem::$discountReason
     */
    public const discount_reason = 'discount_reason';

    /**
     * @see OrderItem::$purchasedAt
     */
    public const datum = 'datum';

    /**
     * @see OrderItem::$customer
     */
    public const id_uzivatele = 'id_uzivatele';

    /**
     * @see OrderItem::$orderer
     */
    public const id_objednatele = 'id_objednatele';

    /**
     * @see OrderItem::$order
     */
    public const order_id = 'order_id';

    /**
     * @see OrderItem::$product
     */
    public const id_predmetu = 'id_predmetu';

    /**
     * @see OrderItem::$variant
     */
    public const variant_id = 'variant_id';
}
