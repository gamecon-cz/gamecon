<?php

declare(strict_types=1);

namespace App\Dto\Kfc;

class KfcGridCellOutputDto
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $typ,
        public readonly ?string $text,
        public readonly ?string $barva,
        public readonly ?string $barvaText,
        public readonly ?int $cilId,
    ) {
    }
}
