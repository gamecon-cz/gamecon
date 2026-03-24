<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\ShopPurchaseCancelled
 */
class ShopPurchaseCancelledEntityStructure
{
    /**
     * @see ShopPurchaseCancelled::$deletedShopPurchaseId
     */
    public const deletedShopPurchaseId = 'deletedShopPurchaseId';

    /**
     * @see ShopPurchaseCancelled::$rocnik
     */
    public const rocnik = 'rocnik';

    /**
     * @see ShopPurchaseCancelled::$cenaNakupni
     */
    public const cenaNakupni = 'cenaNakupni';

    /**
     * @see ShopPurchaseCancelled::$datumNakupu
     */
    public const datumNakupu = 'datumNakupu';

    /**
     * @see ShopPurchaseCancelled::$datumZruseni
     */
    public const datumZruseni = 'datumZruseni';

    /**
     * @see ShopPurchaseCancelled::$zdrojZruseni
     */
    public const zdrojZruseni = 'zdrojZruseni';

    /**
     * @see ShopPurchaseCancelled::$customer
     */
    public const customer = 'customer';

    /**
     * @see ShopPurchaseCancelled::$shopItem
     */
    public const shopItem = 'shopItem';
}
