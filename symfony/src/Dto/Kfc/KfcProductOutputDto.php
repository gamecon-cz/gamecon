<?php

declare(strict_types=1);

namespace App\Dto\Kfc;

class KfcProductOutputDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly int $price,
        public readonly ?int $remaining,
    ) {
    }
}
