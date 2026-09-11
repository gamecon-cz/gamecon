<?php

declare(strict_types=1);

namespace App\Dto\Kfc;

class KfcGridOutputDto
{
    /**
     * @param KfcGridCellOutputDto[] $bunky
     */
    public function __construct(
        public readonly ?int $id,
        public readonly ?string $text,
        public readonly array $bunky,
    ) {
    }
}
