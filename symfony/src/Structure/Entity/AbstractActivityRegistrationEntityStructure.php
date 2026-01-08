<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\AbstractActivityRegistration
 */
class AbstractActivityRegistrationEntityStructure
{
    /**
     * @see AbstractActivityRegistration::$id
     */
    public const id = 'id';

    /**
     * @see AbstractActivityRegistration::$activity
     */
    public const activity = 'activity';

    /**
     * @see AbstractActivityRegistration::$registeredUser
     */
    public const registeredUser = 'registeredUser';

    /**
     * @see AbstractActivityRegistration::$activityRegistrationState
     */
    public const activityRegistrationState = 'activityRegistrationState';
}
