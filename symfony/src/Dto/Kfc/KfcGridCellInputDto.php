<?php

declare(strict_types=1);

namespace App\Dto\Kfc;

class KfcGridCellInputDto
{
    public ?int $id = null;
    public int $typ = 0;
    public ?string $text = null;
    public ?string $barva = null;
    public ?string $barvaText = null;
    public ?int $cilId = null;
    /**
     * The size or night an item cell sells; it must belong to the product in `cilId`.
     */
    public ?int $variantId = null;
}
