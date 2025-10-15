<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\ReportUsageLog
 */
class ReportUsageLogSqlStructure
{
    /**
     * @see ReportUsageLog
     */
    public const _table = 'reporty_log_pouziti';

    /**
     * @see ReportUsageLog::$id
     */
    public const id = 'id';

    /**
     * @see ReportUsageLog::$format
     */
    public const format = 'format';

    /**
     * @see ReportUsageLog::$casPouziti
     */
    public const cas_pouziti = 'cas_pouziti';

    /**
     * @see ReportUsageLog::$casovaZona
     */
    public const casova_zona = 'casova_zona';

    /**
     * @see ReportUsageLog::$report
     */
    public const id_reportu = 'id_reportu';

    /**
     * @see ReportUsageLog::$usedBy
     */
    public const id_uzivatele = 'id_uzivatele';
}
