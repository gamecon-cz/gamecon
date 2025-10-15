<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\ReportUsageLog
 */
class ReportUsageLogEntityStructure
{
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
    public const casPouziti = 'casPouziti';

    /**
     * @see ReportUsageLog::$casovaZona
     */
    public const casovaZona = 'casovaZona';

    /**
     * @see ReportUsageLog::$report
     */
    public const report = 'report';

    /**
     * @see ReportUsageLog::$usedBy
     */
    public const usedBy = 'usedBy';
}
