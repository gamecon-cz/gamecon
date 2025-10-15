<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\ShopPurchaseCancelled
 */
class ShopPurchaseCancelledSqlStructure
{
    /**
     * @see ShopPurchaseCancelled
     */
    public const _table = 'shop_nakupy_zrusene';

    /**
     * @see ShopPurchaseCancelled::$deletedShopPurchaseId
     */
    public const id_nakupu = 'id_nakupu';

    /**
     * @see ShopPurchaseCancelled::$rocnik
     */
    public const rocnik = 'rocnik';

    /**
     * @see ShopPurchaseCancelled::$cenaNakupni
     */
    public const cena_nakupni = 'cena_nakupni';

    /**
     * @see ShopPurchaseCancelled::$datumNakupu
     */
    public const datum_nakupu = 'datum_nakupu';

    /**
     * @see ShopPurchaseCancelled::$datumZruseni
     */
    public const datum_zruseni = 'datum_zruseni';

    /**
     * @see ShopPurchaseCancelled::$zdrojZruseni
     */
    public const zdroj_zruseni = 'zdroj_zruseni';

    /**
     * @see ShopPurchaseCancelled::$customer
     */
    public const id_uzivatele = 'id_uzivatele';

    /**
     * @see ShopPurchaseCancelled::$shopItem
     */
    public const id_predmetu = 'id_predmetu';
}
