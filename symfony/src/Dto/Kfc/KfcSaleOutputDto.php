<?php

declare(strict_types=1);

namespace App\Dto\Kfc;

class KfcSaleOutputDto
{
    public function __construct(
        public readonly int $soldItems,
        public readonly string $totalPrice,
    ) {
    }
}
