<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\ActivityStatus
 */
class ActivityStatusSqlStructure
{
    /**
     * @see ActivityStatus
     */
    public const _table = 'akce_stav';

    /**
     * @see ActivityStatus::$id
     */
    public const id_stav = 'id_stav';

    /**
     * @see ActivityStatus::$nazev
     */
    public const nazev = 'nazev';
}
