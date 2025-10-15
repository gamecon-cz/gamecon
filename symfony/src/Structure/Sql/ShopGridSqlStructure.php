<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\ShopGrid
 */
class ShopGridSqlStructure
{
    /**
     * @see ShopGrid
     */
    public const _table = 'obchod_mrizky';

    /**
     * @see ShopGrid::$id
     */
    public const id = 'id';

    /**
     * @see ShopGrid::$text
     */
    public const text = 'text';
}
