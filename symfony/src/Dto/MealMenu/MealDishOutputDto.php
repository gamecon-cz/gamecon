<?php

declare(strict_types=1);

namespace App\Dto\MealMenu;

use App\Entity\MealDish;

class MealDishOutputDto
{
    public int $id;

    public string $name;

    public ?string $description = null;

    public bool $vegetarian = false;

    /**
     * Tohle dostane, kdo si do termínu nevybere.
     */
    public bool $default = false;

    public static function fromDish(MealDish $dish, bool $isDefault): self
    {
        $dto = new self();
        $dto->id = $dish->getId() ?? throw new \LogicException('Jídlo z jídelníčku ještě nemá id.');
        $dto->name = $dish->getName();
        $dto->description = $dish->getDescription();
        $dto->vegetarian = $dish->isVegetarian();
        $dto->default = $isDefault;

        return $dto;
    }
}
