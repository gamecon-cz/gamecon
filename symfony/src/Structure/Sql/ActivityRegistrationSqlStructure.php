<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\ActivityRegistration
 */
class ActivityRegistrationSqlStructure
{
    public const _table = 'akce_prihlaseni';

    /**
     * @see ActivityRegistration::$id
     */
    public const id = 'id';

    /**
     * @see ActivityRegistration::$activity
     */
    public const id_akce = 'id_akce';

    /**
     * @see ActivityRegistration::$registeredUser
     */
    public const id_uzivatele = 'id_uzivatele';

    /**
     * @see ActivityRegistration::$activityRegistrationState
     */
    public const id_stavu_prihlaseni = 'id_stavu_prihlaseni';
}
