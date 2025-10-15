<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\Page
 */
class PageSqlStructure
{
    /**
     * @see Page
     */
    public const _table = 'stranky';

    /**
     * @see Page::$id
     */
    public const id_stranky = 'id_stranky';

    /**
     * @see Page::$urlStranky
     */
    public const url_stranky = 'url_stranky';

    /**
     * @see Page::$obsah
     */
    public const obsah = 'obsah';

    /**
     * @see Page::$poradi
     */
    public const poradi = 'poradi';
}
