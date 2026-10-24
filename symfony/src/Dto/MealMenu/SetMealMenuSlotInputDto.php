<?php

declare(strict_types=1);

namespace App\Dto\MealMenu;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Jídla jednoho obědu nebo večeře, celá najednou: co v seznamu chybí, se smaže.
 */
class SetMealMenuSlotInputDto
{
    #[Assert\NotNull(message: 'Musí být zadána porce jídla')]
    #[Assert\Positive(message: 'Porce jídla musí být kladné číslo varianty')]
    public ?int $variantId = null;

    /**
     * @var MealDishInputDto[]
     */
    #[Assert\Valid]
    public array $dishes = [];
}
