<?php

declare(strict_types=1);

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class SetCustomerMealsInputDto
{
    #[Assert\NotNull(message: 'Musí být zadán účastník')]
    #[Assert\Positive(message: 'ID účastníka musí být kladné číslo')]
    public ?int $customerId = null;

    /**
     * The meals the customer ends up with. An empty array cancels them all.
     *
     * @var int[]
     */
    #[Assert\NotNull(message: 'Seznam jídel musí být vyplněn')]
    #[Assert\All([
        new Assert\Type(type: 'integer', message: 'Jídlo musí být číslo varianty'),
        new Assert\Positive(message: 'Jídlo musí být kladné číslo varianty'),
    ])]
    public array $variantIds = [];
}
