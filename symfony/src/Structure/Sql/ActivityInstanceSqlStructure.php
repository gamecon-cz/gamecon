<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\ActivityInstance
 */
class ActivityInstanceSqlStructure
{
    /**
     * @see ActivityInstance
     */
    public const _table = 'akce_instance';

    /**
     * @see ActivityInstance::$id
     */
    public const id_instance = 'id_instance';

    /**
     * @see ActivityInstance::$mainActivity
     */
    public const id_hlavni_akce = 'id_hlavni_akce';
}
