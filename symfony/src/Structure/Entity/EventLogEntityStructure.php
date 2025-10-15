<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\EventLog
 */
class EventLogEntityStructure
{
    /**
     * @see EventLog::$id
     */
    public const id = 'id';

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
    public const loggedBy = 'loggedBy';
}
