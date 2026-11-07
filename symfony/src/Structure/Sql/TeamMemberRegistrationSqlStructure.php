<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\TeamMemberRegistration
 */
class TeamMemberRegistrationSqlStructure
{
    public const _table = 'akce_tym_prihlaseni';

    /**
     * @see TeamMemberRegistration::$id
     */
    public const id = 'id';

    /**
     * @see TeamMemberRegistration::$uzivatel
     */
    public const id_uzivatele = 'id_uzivatele';

    /**
     * @see TeamMemberRegistration::$team
     */
    public const id_tymu = 'id_tymu';
}
