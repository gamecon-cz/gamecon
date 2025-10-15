<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\QuickReport
 */
class QuickReportSqlStructure
{
    /**
     * @see QuickReport
     */
    public const _table = 'reporty_quick';

    /**
     * @see QuickReport::$id
     */
    public const id = 'id';

    /**
     * @see QuickReport::$nazev
     */
    public const nazev = 'nazev';

    /**
     * @see QuickReport::$dotaz
     */
    public const dotaz = 'dotaz';

    /**
     * @see QuickReport::$formatXlsx
     */
    public const format_xlsx = 'format_xlsx';

    /**
     * @see QuickReport::$formatHtml
     */
    public const format_html = 'format_html';
}
