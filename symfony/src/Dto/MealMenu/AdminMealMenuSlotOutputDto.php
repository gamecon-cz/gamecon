<?php

declare(strict_types=1);

namespace App\Dto\MealMenu;

class AdminMealMenuSlotOutputDto
{
    public int $variantId;

    public int $day;

    public string $dayName;

    public string $name;

    /**
     * Kolik porcí je v tomhle slotu prodáno.
     */
    public int $portionCount = 0;

    /**
     * Kolik z nich si zatím jídlo nevybralo, a dostane proto výchozí.
     */
    public int $unchosenCount = 0;

    /**
     * @var AdminMealDishOutputDto[]
     */
    public array $dishes = [];
}
