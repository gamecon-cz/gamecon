<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\Tournament
 */
class TournamentSqlStructure
{
    public const _table = 'turnaje';

    /**
     * @see Tournament::$id
     */
    public const id_turnaje = 'id_turnaje';

    /**
     * @see Tournament::$nazev
     */
    public const nazev = 'nazev';

    /**
     * @see Tournament::$rok
     */
    public const rok = 'rok';
}
