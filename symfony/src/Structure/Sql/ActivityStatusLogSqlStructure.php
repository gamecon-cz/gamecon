<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\ActivityStatusLog
 */
class ActivityStatusLogSqlStructure
{
    /**
     * @see ActivityStatusLog
     */
    public const _table = 'akce_stavy_log';

    /**
     * @see ActivityStatusLog::$id
     */
    public const akce_stavy_log_id = 'akce_stavy_log_id';

    /**
     * @see ActivityStatusLog::$kdy
     */
    public const kdy = 'kdy';

    /**
     * @see ActivityStatusLog::$activity
     */
    public const id_akce = 'id_akce';

    /**
     * @see ActivityStatusLog::$status
     */
    public const id_stav = 'id_stav';
}
