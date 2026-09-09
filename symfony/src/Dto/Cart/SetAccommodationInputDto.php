<?php

declare(strict_types=1);

namespace App\Dto\Cart;

use Symfony\Component\Validator\Constraints as Assert;

class SetAccommodationInputDto
{
    /**
     * The nights the customer wants to end up with. An empty array cancels the whole
     * booking, which is how "I don't want accommodation" is expressed.
     *
     * @var int[]
     */
    #[Assert\NotNull(message: 'Seznam nocí musí být vyplněn')]
    #[Assert\All([new Assert\Type(type: 'integer', message: 'Noc musí být číslo varianty')])]
    public array $variantIds = [];
}
