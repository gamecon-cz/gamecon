<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\ShopPurchase
 */
class ShopPurchaseSqlStructure
{
    /**
     * @see ShopPurchase
     */
    public const _table = 'shop_nakupy';

    /**
     * @see ShopPurchase::$idNakupu
     */
    public const id_nakupu = 'id_nakupu';

    /**
     * @see ShopPurchase::$rok
     */
    public const rok = 'rok';

    /**
     * @see ShopPurchase::$cenaNakupni
     */
    public const cena_nakupni = 'cena_nakupni';

    /**
     * @see ShopPurchase::$datum
     */
    public const datum = 'datum';

    /**
     * @see ShopPurchase::$customer
     */
    public const id_uzivatele = 'id_uzivatele';

    /**
     * @see ShopPurchase::$orderer
     */
    public const id_objednatele = 'id_objednatele';

    /**
     * @see ShopPurchase::$shopItem
     */
    public const id_predmetu = 'id_predmetu';
}
