<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\ActivityRegistration
 */
class ActivityRegistrationEntityStructure
{
    /**
     * @see ActivityRegistration::$activity
     */
    public const activity = 'activity';

    /**
     * @see ActivityRegistration::$user
     */
    public const user = 'user';

    /**
     * @see ActivityRegistration::$activityRegistrationState
     */
    public const activityRegistrationState = 'activityRegistrationState';
}
