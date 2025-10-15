<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\Accommodation
 */
class AccommodationEntityStructure
{
    /**
     * @see Accommodation::$rok
     */
    public const rok = 'rok';

    /**
     * @see Accommodation::$den
     */
    public const den = 'den';

    /**
     * @see Accommodation::$pokoj
     */
    public const pokoj = 'pokoj';

    /**
     * @see Accommodation::$uzivatel
     */
    public const uzivatel = 'uzivatel';
}
