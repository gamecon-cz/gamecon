<?php

declare(strict_types=1);

namespace App\Dto\MealMenu;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Výběr jídla z pultu: zákazník jde v payloadu, token patří obsluze.
 */
class ChooseCustomerMealDishInputDto extends ChooseMealDishInputDto
{
    #[Assert\NotNull(message: 'Musí být zadán účastník')]
    #[Assert\Positive(message: 'ID účastníka musí být kladné číslo')]
    public ?int $customerId = null;
}
