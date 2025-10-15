<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\Report
 */
class ReportSqlStructure
{
    /**
     * @see Report
     */
    public const _table = 'reporty';

    /**
     * @see Report::$id
     */
    public const id = 'id';

    /**
     * @see Report::$skript
     */
    public const skript = 'skript';

    /**
     * @see Report::$nazev
     */
    public const nazev = 'nazev';

    /**
     * @see Report::$formatXlsx
     */
    public const format_xlsx = 'format_xlsx';

    /**
     * @see Report::$formatHtml
     */
    public const format_html = 'format_html';

    /**
     * @see Report::$viditelny
     */
    public const viditelny = 'viditelny';
}
