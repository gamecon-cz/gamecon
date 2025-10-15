<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\Location
 */
class LocationSqlStructure
{
    /**
     * @see Location
     */
    public const _table = 'akce_lokace';

    /**
     * @see Location::$id
     */
    public const id_lokace = 'id_lokace';

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
