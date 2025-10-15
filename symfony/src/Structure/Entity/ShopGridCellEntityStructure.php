<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\ShopGridCell
 */
class ShopGridCellEntityStructure
{
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
    public const barvaText = 'barvaText';

    /**
     * @see ShopGridCell::$cilId
     */
    public const cilId = 'cilId';

    /**
     * @see ShopGridCell::$shopGrid
     */
    public const shopGrid = 'shopGrid';
}
