<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\ShopGridCell
 */
class ShopGridCellSqlStructure
{
    /**
     * @see ShopGridCell
     */
    public const _table = 'obchod_bunky';

    /**
     * @see ShopGridCell::$id
     */
    public const id = 'id';

    /**
     * @see ShopGridCell::$typ
     */
    public const typ = 'typ';

    /**
     * @see ShopGridCell::$text
     */
    public const text = 'text';

    /**
     * @see ShopGridCell::$barva
     */
    public const barva = 'barva';

    /**
     * @see ShopGridCell::$barvaText
     */
    public const barva_text = 'barva_text';

    /**
     * @see ShopGridCell::$cilId
     */
    public const cil_id = 'cil_id';

    /**
     * @see ShopGridCell::$shopGrid
     */
    public const mrizka_id = 'mrizka_id';
}
