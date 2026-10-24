<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\MealDish
 */
class MealDishSqlStructure
{
    public const _table = 'meal_dish';

    /**
     * @see MealDish::$id
     */
    public const id = 'id';

    /**
     * @see MealDish::$year
     */
    public const year = 'year';

    /**
     * @see MealDish::$name
     */
    public const name = 'name';

    /**
     * @see MealDish::$description
     */
    public const description = 'description';

    /**
     * @see MealDish::$vegetarian
     */
    public const vegetarian = 'vegetarian';

    /**
     * @see MealDish::$default
     */
    public const is_default = 'is_default';

    /**
     * @see MealDish::$position
     */
    public const position = 'position';

    /**
     * @see MealDish::$createdAt
     */
    public const created_at = 'created_at';

    /**
     * @see MealDish::$updatedAt
     */
    public const updated_at = 'updated_at';

    /**
     * @see MealDish::$variant
     */
    public const variant_id = 'variant_id';
}
