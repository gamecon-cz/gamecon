<?php

declare(strict_types=1);

namespace App\Dto\Cart;

/** One night of one room type. */
class AccommodationCellOutputDto
{
    public int $variantId;

    public bool $selected = false;

    /**
     * Null means unlimited — dorm beds are effectively uncapped and the grid shows no
     * number for them.
     */
    public ?int $remaining = null;

    public bool $soldOut = false;

    /**
     * Cannot be ticked: sold out, past the deadline, or a night this customer may not
     * order (Sunday needs its own right). Already-booked nights stay selectable so the
     * customer can drop them.
     */
    public bool $locked = false;
}
