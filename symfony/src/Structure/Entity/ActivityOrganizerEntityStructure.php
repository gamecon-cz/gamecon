<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\ActivityOrganizer
 */
class ActivityOrganizerEntityStructure
{
    /**
     * @see ActivityOrganizer::$activity
     */
    public const activity = 'activity';

    /**
     * @see ActivityOrganizer::$user
     */
    public const user = 'user';
}
