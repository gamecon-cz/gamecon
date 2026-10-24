<?php

declare(strict_types=1);

namespace App\Dto\MealMenu;

use Symfony\Component\Validator\Constraints as Assert;

class ChooseMealDishInputDto
{
    #[Assert\NotNull(message: 'Musí být zadána porce')]
    #[Assert\Positive(message: 'Porce musí být kladné číslo')]
    public ?int $orderItemId = null;

    /**
     * NULL vrátí porci k výchozímu jídlu.
     */
    #[Assert\Positive(message: 'Jídlo musí být kladné číslo')]
    public ?int $dishId = null;
}
