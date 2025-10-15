<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\ActivityOrganizer
 */
class ActivityOrganizerSqlStructure
{
    /**
     * @see ActivityOrganizer
     */
    public const _table = 'akce_organizatori';

    /**
     * @see ActivityOrganizer::$activity
     */
    public const id_akce = 'id_akce';

    /**
     * @see ActivityOrganizer::$user
     */
    public const id_uzivatele = 'id_uzivatele';
}
