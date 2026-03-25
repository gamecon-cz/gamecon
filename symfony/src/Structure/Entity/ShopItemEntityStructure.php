<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\ShopItem
 */
class ShopItemEntityStructure
{
    /**
     * @see ShopItem::$id
     */
    public const id = 'id';

    /**
     * @see ShopItem::$nazev
     */
    public const nazev = 'nazev';

    /**
     * @see ShopItem::$kodPredmetu
     */
    public const kodPredmetu = 'kodPredmetu';

    /**
     * @see ShopItem::$cenaAktualni
     */
    public const cenaAktualni = 'cenaAktualni';

    /**
     * @see ShopItem::$stav
     */
    public const stav = 'stav';

    /**
     * @see ShopItem::$nabizetDo
     */
    public const nabizetDo = 'nabizetDo';

    /**
     * @see ShopItem::$kusuVyrobeno
     */
    public const kusuVyrobeno = 'kusuVyrobeno';

    /**
     * @see ShopItem::$ubytovaniDen
     */
    public const ubytovaniDen = 'ubytovaniDen';

    /**
     * @see ShopItem::$popis
     */
    public const popis = 'popis';

    /**
     * @see ShopItem::$archivedAt
     */
    public const archivedAt = 'archivedAt';

    /**
     * @see ShopItem::$reservedForOrganizers
     */
    public const reservedForOrganizers = 'reservedForOrganizers';

    /**
     * @see ShopItem::$vedlejsi
     */
    public const vedlejsi = 'vedlejsi';
}
