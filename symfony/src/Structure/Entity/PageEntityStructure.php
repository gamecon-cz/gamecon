<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\Page
 */
class PageEntityStructure
{
    /**
     * @see Page::$id
     */
    public const id = 'id';

    /**
     * @see Page::$urlStranky
     */
    public const urlStranky = 'urlStranky';

    /**
     * @see Page::$obsah
     */
    public const obsah = 'obsah';

    /**
     * @see Page::$obsahEn
     */
    public const obsahEn = 'obsahEn';

    /**
     * @see Page::$poradi
     */
    public const poradi = 'poradi';
}
