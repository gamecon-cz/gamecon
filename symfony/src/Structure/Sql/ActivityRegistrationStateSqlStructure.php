<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\ActivityRegistrationState
 */
class ActivityRegistrationStateSqlStructure
{
    /**
     * @see ActivityRegistrationState
     */
    public const _table = 'akce_prihlaseni_stavy';

    /**
     * @see ActivityRegistrationState::$id
     */
    public const id_stavu_prihlaseni = 'id_stavu_prihlaseni';

    /**
     * @see ActivityRegistrationState::$nazev
     */
    public const nazev = 'nazev';

    /**
     * @see ActivityRegistrationState::$platbaProcent
     */
    public const platba_procent = 'platba_procent';
}
