<?php

declare(strict_types=1);

namespace App\Dto\Kfc;

use Symfony\Component\Validator\Constraints as Assert;

class KfcSaleItemInputDto
{
    #[Assert\NotBlank]
    #[Assert\Positive]
    public ?int $productId = null;

    #[Assert\NotBlank]
    #[Assert\Positive]
    public int $quantity = 1;
}
