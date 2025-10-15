<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\QuickReport
 */
class QuickReportEntityStructure
{
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
    public const formatXlsx = 'formatXlsx';

    /**
     * @see QuickReport::$formatHtml
     */
    public const formatHtml = 'formatHtml';
}
