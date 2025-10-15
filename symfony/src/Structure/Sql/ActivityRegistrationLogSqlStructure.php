<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\ActivityRegistrationLog
 */
class ActivityRegistrationLogSqlStructure
{
    /**
     * @see ActivityRegistrationLog
     */
    public const _table = 'akce_prihlaseni_log';

    /**
     * @see ActivityRegistrationLog::$id
     */
    public const id_log = 'id_log';

    /**
     * @see ActivityRegistrationLog::$kdy
     */
    public const kdy = 'kdy';

    /**
     * @see ActivityRegistrationLog::$typ
     */
    public const typ = 'typ';

    /**
     * @see ActivityRegistrationLog::$zdrojZmeny
     */
    public const zdroj_zmeny = 'zdroj_zmeny';

    /**
     * @see ActivityRegistrationLog::$rocnik
     */
    public const rocnik = 'rocnik';

    /**
     * @see ActivityRegistrationLog::$activity
     */
    public const id_akce = 'id_akce';

    /**
     * @see ActivityRegistrationLog::$registeredUser
     */
    public const id_uzivatele = 'id_uzivatele';

    /**
     * @see ActivityRegistrationLog::$changedBy
     */
    public const id_zmenil = 'id_zmenil';
}
