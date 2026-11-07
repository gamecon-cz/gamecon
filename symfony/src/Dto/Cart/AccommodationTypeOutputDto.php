<?php

declare(strict_types=1);

namespace App\Dto\Cart;

class AccommodationTypeOutputDto
{
    public int $productId;

    public string $name;

    public string $description = '';

    public string $price;

    /**
     * One figure for the whole row: the discount applies to the type, not to a single night.
     */
    public string $discountedPrice;

    /**
     * Name of the rule that lowered `discountedPrice`. Like the price it is worked out without a night, so a rule
     * that frees only some nights shows neither.
     */
    public ?string $discountReason = null;

    /**
     * Cells keyed by day index (0 = Wednesday). A day the type is not offered on has no
     * entry rather than a disabled one — the grid renders those as blank.
     *
     * @var array<int, AccommodationCellOutputDto>
     */
    public array $nights = [];
}
