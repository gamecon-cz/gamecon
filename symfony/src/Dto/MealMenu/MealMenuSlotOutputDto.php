<?php

declare(strict_types=1);

namespace App\Dto\MealMenu;

class MealMenuSlotOutputDto
{
    public int $variantId;

    /**
     * Den GameConu jako u ubytování: 0 = středa, 1 = čtvrtek …
     */
    public int $day;

    public string $dayName;

    /**
     * Název porce, např. „Oběd pátek".
     */
    public string $name;

    /**
     * @var MealDishOutputDto[]
     */
    public array $dishes = [];

    /**
     * Porce, které má zákazník v tomhle slotu koupené. Obvykle jedna, nebo žádná.
     *
     * @var MealPortionOutputDto[]
     */
    public array $portions = [];
}
