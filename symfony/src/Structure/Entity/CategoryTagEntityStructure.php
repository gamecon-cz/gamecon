<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\CategoryTag
 */
class CategoryTagEntityStructure
{
    /**
     * @see CategoryTag::$id
     */
    public const id = 'id';

    /**
     * @see CategoryTag::$nazev
     */
    public const nazev = 'nazev';

    /**
     * @see CategoryTag::$poradi
     */
    public const poradi = 'poradi';

    /**
     * @see CategoryTag::$mainCategoryTag
     */
    public const mainCategoryTag = 'mainCategoryTag';

    /**
     * @see CategoryTag::$tags
     */
    public const tags = 'tags';
}
