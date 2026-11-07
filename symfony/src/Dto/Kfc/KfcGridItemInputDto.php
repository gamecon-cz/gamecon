<?php

declare(strict_types=1);

namespace App\Dto\Kfc;

class KfcGridItemInputDto
{
    public ?int $id = null;
    public ?string $text = null;

    /**
     * @var KfcGridCellInputDto[]
     */
    public array $bunky = [];
}
