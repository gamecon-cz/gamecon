<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\ActivityRegistrationSpec
 */
class ActivityRegistrationSpecSqlStructure
{
    /**
     * @see ActivityRegistrationSpec
     */
    public const _table = 'akce_prihlaseni_spec';

    /**
     * @see ActivityRegistrationSpec::$id
     */
    public const id = 'id';

    /**
     * @see ActivityRegistrationSpec::$activity
     */
    public const id_akce = 'id_akce';

    /**
     * @see ActivityRegistrationSpec::$registeredUser
     */
    public const id_uzivatele = 'id_uzivatele';

    /**
     * @see ActivityRegistrationSpec::$activityRegistrationState
     */
    public const id_stavu_prihlaseni = 'id_stavu_prihlaseni';
}
