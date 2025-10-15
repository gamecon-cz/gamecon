<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\Accommodation
 */
class AccommodationSqlStructure
{
    /**
     * @see Accommodation
     */
    public const _table = 'ubytovani';

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
    public const id_uzivatele = 'id_uzivatele';
}
