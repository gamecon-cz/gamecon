<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel\Dto;

readonly class PriceAfterDiscountDto
{
    public function __construct(
        public int | float $finalPrice,
        public int | float $discount,
    ) {
    }
}
