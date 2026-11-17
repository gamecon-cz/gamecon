<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\Costume
 */
class CostumeEntityStructure
{
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
    public const interniPoznamka = 'interniPoznamka';

    /**
     * @see Costume::$poradi
     */
    public const poradi = 'poradi';

    /**
     * @see Costume::$fotky
     */
    public const fotky = 'fotky';
}
