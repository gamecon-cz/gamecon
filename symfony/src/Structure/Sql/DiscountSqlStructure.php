<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\Discount
 */
class DiscountSqlStructure
{
    /**
     * @see Discount
     */
    public const _table = 'slevy';

    /**
     * @see Discount::$id
     */
    public const id = 'id';

    /**
     * @see Discount::$castka
     */
    public const castka = 'castka';

    /**
     * @see Discount::$rok
     */
    public const rok = 'rok';

    /**
     * @see Discount::$provedeno
     */
    public const provedeno = 'provedeno';

    /**
     * @see Discount::$poznamka
     */
    public const poznamka = 'poznamka';

    /**
     * @see Discount::$user
     */
    public const id_uzivatele = 'id_uzivatele';

    /**
     * @see Discount::$madeBy
     */
    public const provedl = 'provedl';
}
