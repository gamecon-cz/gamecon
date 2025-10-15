<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\Discount
 */
class DiscountEntityStructure
{
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
    public const user = 'user';

    /**
     * @see Discount::$madeBy
     */
    public const madeBy = 'madeBy';
}
