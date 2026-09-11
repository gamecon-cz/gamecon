<?php

declare(strict_types=1);

namespace App\Dto\Cart;

class MerchProductOutputDto
{
    public string $name;
    public string $description;
    public int $variantId;

    /**
     * List price before any role discount, so the UI can strike it through when
     * discountedPrice differs.
     */
    public string $price;
    public string $discountedPrice;

    /**
     * How many the customer already owns this year — the grid pre-fills its input with it.
     */
    public int $purchasedQuantity;

    /**
     * Highest quantity the customer may end up owning, already including what they
     * bought, or null when the product has unlimited stock.
     */
    public ?int $maxQuantity;
    public bool $secondary;
    public bool $available;
}
