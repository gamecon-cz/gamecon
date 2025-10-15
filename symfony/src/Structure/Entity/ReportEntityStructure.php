<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\Report
 */
class ReportEntityStructure
{
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
    public const formatXlsx = 'formatXlsx';

    /**
     * @see Report::$formatHtml
     */
    public const formatHtml = 'formatHtml';

    /**
     * @see Report::$viditelny
     */
    public const viditelny = 'viditelny';
}
