<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\BulkActivityLog
 */
class BulkActivityLogEntityStructure
{
    /**
     * @see BulkActivityLog::$id
     */
    public const id = 'id';

    /**
     * @see BulkActivityLog::$skupina
     */
    public const skupina = 'skupina';

    /**
     * @see BulkActivityLog::$akce
     */
    public const akce = 'akce';

    /**
     * @see BulkActivityLog::$vysledek
     */
    public const vysledek = 'vysledek';

    /**
     * @see BulkActivityLog::$kdy
     */
    public const kdy = 'kdy';

    /**
     * @see BulkActivityLog::$madeBy
     */
    public const madeBy = 'madeBy';
}
