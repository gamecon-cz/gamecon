<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\ActivityRegistrationLog
 */
class ActivityRegistrationLogEntityStructure
{
    /**
     * @see ActivityRegistrationLog::$id
     */
    public const id = 'id';

    /**
     * @see ActivityRegistrationLog::$kdy
     */
    public const kdy = 'kdy';

    /**
     * @see ActivityRegistrationLog::$typ
     */
    public const typ = 'typ';

    /**
     * @see ActivityRegistrationLog::$zdrojZmeny
     */
    public const zdrojZmeny = 'zdrojZmeny';

    /**
     * @see ActivityRegistrationLog::$rocnik
     */
    public const rocnik = 'rocnik';

    /**
     * @see ActivityRegistrationLog::$activity
     */
    public const activity = 'activity';

    /**
     * @see ActivityRegistrationLog::$registeredUser
     */
    public const registeredUser = 'registeredUser';

    /**
     * @see ActivityRegistrationLog::$changedBy
     */
    public const changedBy = 'changedBy';
}
