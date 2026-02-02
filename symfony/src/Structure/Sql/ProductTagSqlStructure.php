<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\ProductTag
 */
class ProductTagSqlStructure
{
    public const _table = 'product_tag';

    /**
     * @see ProductTag::$id
     */
    public const id = 'id';

    /**
     * @see ProductTag::$code
     */
    public const code = 'code';

    /**
     * @see ProductTag::$name
     */
    public const name = 'name';

    /**
     * @see ProductTag::$description
     */
    public const description = 'description';

    /**
     * @see ProductTag::$createdAt
     */
    public const created_at = 'created_at';

    /**
     * @see ProductTag::$updatedAt
     */
    public const updated_at = 'updated_at';
}
