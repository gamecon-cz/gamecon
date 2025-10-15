<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\ShopItem
 */
class ShopItemSqlStructure
{
    /**
     * @see ShopItem
     */
    public const _table = 'shop_predmety';

    /**
     * @see ShopItem::$id
     */
    public const id_predmetu = 'id_predmetu';

    /**
     * @see ShopItem::$nazev
     */
    public const nazev = 'nazev';

    /**
     * @see ShopItem::$kodPredmetu
     */
    public const kod_predmetu = 'kod_predmetu';

    /**
     * @see ShopItem::$modelRok
     */
    public const model_rok = 'model_rok';

    /**
     * @see ShopItem::$cenaAktualni
     */
    public const cena_aktualni = 'cena_aktualni';

    /**
     * @see ShopItem::$stav
     */
    public const stav = 'stav';

    /**
     * @see ShopItem::$nabizetDo
     */
    public const nabizet_do = 'nabizet_do';

    /**
     * @see ShopItem::$kusuVyrobeno
     */
    public const kusu_vyrobeno = 'kusu_vyrobeno';

    /**
     * @see ShopItem::$typ
     */
    public const typ = 'typ';

    /**
     * @see ShopItem::$ubytovaniDen
     */
    public const ubytovani_den = 'ubytovani_den';

    /**
     * @see ShopItem::$popis
     */
    public const popis = 'popis';

    /**
     * @see ShopItem::$jeLetosniHlavni
     */
    public const je_letosni_hlavni = 'je_letosni_hlavni';
}
