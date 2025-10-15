<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\BulkActivityLog
 */
class BulkActivityLogSqlStructure
{
    /**
     * @see BulkActivityLog
     */
    public const _table = 'hromadne_akce_log';

    /**
     * @see BulkActivityLog::$id
     */
    public const id_logu = 'id_logu';

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
    public const provedl = 'provedl';
}
