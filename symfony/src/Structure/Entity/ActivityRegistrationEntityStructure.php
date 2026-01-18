<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\ActivityRegistration
 */
class ActivityRegistrationEntityStructure
{
    /**
     * @see ActivityRegistration::$id
     */
    public const id = 'id';

    /**
     * @see ActivityRegistration::$activity
     */
    public const activity = 'activity';

    /**
     * @see ActivityRegistration::$registeredUser
     */
    public const registeredUser = 'registeredUser';

    /**
     * @see ActivityRegistration::$activityRegistrationState
     */
    public const activityRegistrationState = 'activityRegistrationState';
}
