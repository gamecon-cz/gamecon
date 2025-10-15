<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\ActivityTag
 */
class ActivityTagEntityStructure
{
    /**
     * @see ActivityTag::$activity
     */
    public const activity = 'activity';

    /**
     * @see ActivityTag::$tag
     */
    public const tag = 'tag';
}
