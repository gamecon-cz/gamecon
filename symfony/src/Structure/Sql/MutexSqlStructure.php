<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\Mutex
 */
class MutexSqlStructure
{
    /**
     * @see Mutex
     */
    public const _table = 'mutex';

    /**
     * @see Mutex::$id
     */
    public const id_mutex = 'id_mutex';

    /**
     * @see Mutex::$akce
     */
    public const akce = 'akce';

    /**
     * @see Mutex::$klic
     */
    public const klic = 'klic';

    /**
     * @see Mutex::$from
     */
    public const od = 'od';

    /**
     * @see Mutex::$to
     */
    public const do = 'do';

    /**
     * @see Mutex::$lockedBy
     */
    public const zamknul = 'zamknul';
}
