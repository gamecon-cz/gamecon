<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\ActivityRegistration
 */
class ActivityRegistrationSqlStructure
{
    /**
     * @see ActivityRegistration
     */
    public const _table = 'akce_prihlaseni';

    /**
     * @see ActivityRegistration::$activity
     */
    public const id_akce = 'id_akce';

    /**
     * @see ActivityRegistration::$user
     */
    public const id_uzivatele = 'id_uzivatele';

    /**
     * @see ActivityRegistration::$activityRegistrationState
     */
    public const id_stavu_prihlaseni = 'id_stavu_prihlaseni';
}
