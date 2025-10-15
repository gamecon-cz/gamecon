<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\ActivityStatusLog
 */
class ActivityStatusLogEntityStructure
{
    /**
     * @see ActivityStatusLog::$id
     */
    public const id = 'id';

    /**
     * @see ActivityStatusLog::$kdy
     */
    public const kdy = 'kdy';

    /**
     * @see ActivityStatusLog::$activity
     */
    public const activity = 'activity';

    /**
     * @see ActivityStatusLog::$status
     */
    public const status = 'status';
}
