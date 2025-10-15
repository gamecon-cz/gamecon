<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\CategoryTag
 */
class CategoryTagSqlStructure
{
    /**
     * @see CategoryTag
     */
    public const _table = 'kategorie_sjednocenych_tagu';

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
    public const id_hlavni_kategorie = 'id_hlavni_kategorie';
}
