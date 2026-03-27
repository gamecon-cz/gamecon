<?php

declare(strict_types=1);

namespace App\Dto\Cart;

use Symfony\Component\Validator\Constraints as Assert;

class AddToCartInputDto
{
    #[Assert\NotBlank(message: 'ID varianty musí být vyplněno')]
    #[Assert\Positive]
    public ?int $variantId = null;

    /**
     * If set, adds the entire bundle instead of a single variant
     */
    #[Assert\Positive]
    public ?int $bundleId = null;
}
