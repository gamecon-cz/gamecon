<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\Location
 */
class LocationEntityStructure
{
    /**
     * @see Location::$id
     */
    public const id = 'id';

    /**
     * @see Location::$nazev
     */
    public const nazev = 'nazev';

    /**
     * @see Location::$dvere
     */
    public const dvere = 'dvere';

    /**
     * @see Location::$poznamka
     */
    public const poznamka = 'poznamka';

    /**
     * @see Location::$poradi
     */
    public const poradi = 'poradi';

    /**
     * @see Location::$rok
     */
    public const rok = 'rok';
}
