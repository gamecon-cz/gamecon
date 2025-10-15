<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\ActivityRegistrationSpec
 */
class ActivityRegistrationSpecEntityStructure
{
    /**
     * @see ActivityRegistrationSpec::$id
     */
    public const id = 'id';

    /**
     * @see ActivityRegistrationSpec::$activity
     */
    public const activity = 'activity';

    /**
     * @see ActivityRegistrationSpec::$registeredUser
     */
    public const registeredUser = 'registeredUser';

    /**
     * @see ActivityRegistrationSpec::$activityRegistrationState
     */
    public const activityRegistrationState = 'activityRegistrationState';
}
