<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\MealDish
 */
class MealDishEntityStructure
{
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
    public const default = 'default';

    /**
     * @see MealDish::$position
     */
    public const position = 'position';

    /**
     * @see MealDish::$createdAt
     */
    public const createdAt = 'createdAt';

    /**
     * @see MealDish::$updatedAt
     */
    public const updatedAt = 'updatedAt';

    /**
     * @see MealDish::$variant
     */
    public const variant = 'variant';
}
