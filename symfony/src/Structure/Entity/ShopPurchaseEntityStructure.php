<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\ShopPurchase
 */
class ShopPurchaseEntityStructure
{
    /**
     * @see ShopPurchase::$idNakupu
     */
    public const idNakupu = 'idNakupu';

    /**
     * @see ShopPurchase::$rok
     */
    public const rok = 'rok';

    /**
     * @see ShopPurchase::$cenaNakupni
     */
    public const cenaNakupni = 'cenaNakupni';

    /**
     * @see ShopPurchase::$datum
     */
    public const datum = 'datum';

    /**
     * @see ShopPurchase::$customer
     */
    public const customer = 'customer';

    /**
     * @see ShopPurchase::$orderer
     */
    public const orderer = 'orderer';

    /**
     * @see ShopPurchase::$shopItem
     */
    public const shopItem = 'shopItem';
}
