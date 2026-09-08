<?php

declare(strict_types=1);

namespace App\Dto\Cart;

/** One row of the accommodation grid: a room type, with a cell per night. */
class AccommodationTypeOutputDto
{
    public int $productId;

    public string $name;

    public string $description = '';

    public string $price;

    /**
     * Room types differ in price only, so the whole row shares one figure.
     */
    public string $discountedPrice;

    /**
     * Cells keyed by day index (0 = Wednesday). A day the type is not offered on has no
     * entry rather than a disabled one — the grid renders those as blank.
     *
     * @var array<int, AccommodationCellOutputDto>
     */
    public array $nights = [];
}
