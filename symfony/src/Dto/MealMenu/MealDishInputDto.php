<?php

declare(strict_types=1);

namespace App\Dto\MealMenu;

use Symfony\Component\Validator\Constraints as Assert;

class MealDishInputDto
{
    /**
     * Uložené jídlo, které se upravuje; NULL = nové.
     */
    #[Assert\Positive(message: 'Jídlo musí být kladné číslo')]
    public ?int $id = null;

    #[Assert\NotBlank(message: 'Název jídla nesmí být prázdný')]
    #[Assert\Length(max: 255, maxMessage: 'Název jídla může mít nejvýš {{ limit }} znaků')]
    public string $name = '';

    #[Assert\Length(max: 255, maxMessage: 'Popis jídla může mít nejvýš {{ limit }} znaků')]
    public ?string $description = null;

    public bool $vegetarian = false;

    public bool $default = false;
}
