<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\Tournament
 */
class TournamentEntityStructure
{
    /**
     * @see Tournament::$id
     */
    public const id = 'id';

    /**
     * @see Tournament::$nazev
     */
    public const nazev = 'nazev';

    /**
     * @see Tournament::$rok
     */
    public const rok = 'rok';

    /**
     * @see Tournament::$aktivity
     */
    public const aktivity = 'aktivity';
}
