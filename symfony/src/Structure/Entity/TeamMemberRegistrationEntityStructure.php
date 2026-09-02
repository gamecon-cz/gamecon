<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\TeamMemberRegistration
 */
class TeamMemberRegistrationEntityStructure
{
    /**
     * @see TeamMemberRegistration::$id
     */
    public const id = 'id';

    /**
     * @see TeamMemberRegistration::$uzivatel
     */
    public const uzivatel = 'uzivatel';

    /**
     * @see TeamMemberRegistration::$team
     */
    public const team = 'team';
}
