<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\Costume
 */
class CostumeSqlStructure
{
    public const _table = 'kostym';

    /**
     * @see Costume::$id
     */
    public const id = 'id';

    /**
     * @see Costume::$nazev
     */
    public const nazev = 'nazev';

    /**
     * @see Costume::$popis
     */
    public const popis = 'popis';

    /**
     * @see Costume::$velikost
     */
    public const velikost = 'velikost';

    /**
     * @see Costume::$miry
     */
    public const miry = 'miry';

    /**
     * @see Costume::$strih
     */
    public const strih = 'strih';

    /**
     * @see Costume::$kategorie
     */
    public const kategorie = 'kategorie';

    /**
     * @see Costume::$stav
     */
    public const stav = 'stav';

    /**
     * @see Costume::$interniPoznamka
     */
    public const interni_poznamka = 'interni_poznamka';

    /**
     * @see Costume::$poradi
     */
    public const poradi = 'poradi';
}
