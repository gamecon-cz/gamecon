<?php

declare(strict_types=1);

namespace App\Dto\MealMenu;

use App\Entity\MealDish;

class AdminMealDishOutputDto
{
    public int $id;

    public string $name;

    public ?string $description = null;

    public bool $vegetarian = false;

    public bool $default = false;

    /**
     * Kolik porcí si tohle jídlo vybralo samo, bez těch, které na něj spadnou jako na výchozí.
     */
    public int $chosenCount = 0;

    public static function fromDish(MealDish $dish, bool $isDefault, int $chosenCount): self
    {
        $dto = new self();
        $dto->id = $dish->getId() ?? throw new \LogicException('Jídlo z jídelníčku ještě nemá id.');
        $dto->name = $dish->getName();
        $dto->description = $dish->getDescription();
        $dto->vegetarian = $dish->isVegetarian();
        $dto->default = $isDefault;
        $dto->chosenCount = $chosenCount;

        return $dto;
    }
}
