<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\Mutex
 */
class MutexEntityStructure
{
    /**
     * @see Mutex::$id
     */
    public const id = 'id';

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
    public const from = 'from';

    /**
     * @see Mutex::$to
     */
    public const to = 'to';

    /**
     * @see Mutex::$lockedBy
     */
    public const lockedBy = 'lockedBy';
}
