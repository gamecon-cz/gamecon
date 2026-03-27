<?php

declare(strict_types=1);

namespace App\Dto\Cart;

class CheckoutInputDto
{
    /**
     * Optional note from customer
     */
    public ?string $note = null;
}
