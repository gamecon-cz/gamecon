<?php

declare(strict_types=1);

namespace App\Dto\Kfc;

use Symfony\Component\Validator\Constraints as Assert;

class KfcSaleItemInputDto
{
    #[Assert\NotBlank]
    #[Assert\Positive]
    public ?int $productId = null;

    /**
     * Prodává se vždycky varianta — `productId` sám o sobě neurčuje, co se má odečíst.
     * Vynechat se smí jen u produktu s jedinou variantou, kde je volba jednoznačná;
     * jinak prodej odmítne, místo aby hádal.
     */
    #[Assert\Positive]
    public ?int $variantId = null;

    #[Assert\NotBlank]
    #[Assert\Positive]
    public int $quantity = 1;
}
