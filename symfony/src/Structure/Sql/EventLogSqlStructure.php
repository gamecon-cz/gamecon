<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\EventLog
 */
class EventLogSqlStructure
{
    /**
     * @see EventLog
     */
    public const _table = 'log_udalosti';

    /**
     * @see EventLog::$id
     */
    public const id_udalosti = 'id_udalosti';

    /**
     * @see EventLog::$zprava
     */
    public const zprava = 'zprava';

    /**
     * @see EventLog::$metadata
     */
    public const metadata = 'metadata';

    /**
     * @see EventLog::$rok
     */
    public const rok = 'rok';

    /**
     * @see EventLog::$loggedBy
     */
    public const id_logujiciho = 'id_logujiciho';
}
