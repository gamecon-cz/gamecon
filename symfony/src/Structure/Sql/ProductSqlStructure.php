<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\Product
 */
class ProductSqlStructure
{
    public const _table = 'shop_predmety';

    /**
     * @see Product::$id
     */
    public const id_predmetu = 'id_predmetu';

    /**
     * @see Product::$name
     */
    public const nazev = 'nazev';

    /**
     * @see Product::$code
     */
    public const kod_predmetu = 'kod_predmetu';

    /**
     * @see Product::$currentPrice
     */
    public const cena_aktualni = 'cena_aktualni';

    /**
     * @see Product::$state
     */
    public const stav = 'stav';

    /**
     * @see Product::$availableUntil
     */
    public const nabizet_do = 'nabizet_do';

    /**
     * @see Product::$producedQuantity
     */
    public const kusu_vyrobeno = 'kusu_vyrobeno';

    /**
     * @see Product::$accommodationDay
     */
    public const ubytovani_den = 'ubytovani_den';

    /**
     * @see Product::$description
     */
    public const popis = 'popis';

    /**
     * @see Product::$archivedAt
     */
    public const archived_at = 'archived_at';

    /**
     * @see Product::$reservedForOrganizers
     */
    public const reserved_for_organizers = 'reserved_for_organizers';
}
