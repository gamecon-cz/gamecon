<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\CancelledOrderItem
 */
class CancelledOrderItemSqlStructure
{
    public const _table = 'shop_nakupy_zrusene';

    /**
     * @see CancelledOrderItem::$id
     */
    public const id_nakupu = 'id_nakupu';

    /**
     * @see CancelledOrderItem::$year
     */
    public const rocnik = 'rocnik';

    /**
     * @see CancelledOrderItem::$purchasePrice
     */
    public const cena_nakupni = 'cena_nakupni';

    /**
     * @see CancelledOrderItem::$purchasedAt
     */
    public const datum_nakupu = 'datum_nakupu';

    /**
     * @see CancelledOrderItem::$cancelledAt
     */
    public const datum_zruseni = 'datum_zruseni';

    /**
     * @see CancelledOrderItem::$cancellationReason
     */
    public const zdroj_zruseni = 'zdroj_zruseni';

    /**
     * @see CancelledOrderItem::$customer
     */
    public const id_uzivatele = 'id_uzivatele';

    /**
     * @see CancelledOrderItem::$product
     */
    public const id_predmetu = 'id_predmetu';
}
