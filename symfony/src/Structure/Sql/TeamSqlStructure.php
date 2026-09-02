<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\Team
 */
class TeamSqlStructure
{
    public const _table = 'akce_tym';

    /**
     * @see Team::$id
     */
    public const id = 'id';

    /**
     * @see Team::$kod
     */
    public const kod = 'kod';

    /**
     * @see Team::$nazev
     */
    public const nazev = 'nazev';

    /**
     * @see Team::$limit
     */
    public const limit = 'limit';

    /**
     * @see Team::$zalozen
     */
    public const zalozen = 'zalozen';

    /**
     * @see Team::$verejny
     */
    public const verejny = 'verejny';

    /**
     * @see Team::$zamceny
     */
    public const zamceny = 'zamceny';

    /**
     * @see Team::$expiruje
     */
    public const expiruje = 'expiruje';

    /**
     * @see Team::$kapitan
     */
    public const id_kapitan = 'id_kapitan';
}
