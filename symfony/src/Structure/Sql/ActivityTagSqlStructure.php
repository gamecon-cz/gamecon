<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\ActivityTag
 */
class ActivityTagSqlStructure
{
    /**
     * @see ActivityTag
     */
    public const _table = 'akce_sjednocene_tagy';

    /**
     * @see ActivityTag::$activity
     */
    public const id_akce = 'id_akce';

    /**
     * @see ActivityTag::$tag
     */
    public const id_tagu = 'id_tagu';
}
