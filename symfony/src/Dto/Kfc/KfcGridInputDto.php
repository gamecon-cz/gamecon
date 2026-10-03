<?php

declare(strict_types=1);

namespace App\Dto\Kfc;

use Symfony\Component\Validator\Constraints as Assert;

class KfcGridInputDto
{
    /**
     * @var KfcGridItemInputDto[]
     */
    #[Assert\NotBlank]
    #[Assert\Valid]
    public array $grids = [];
}
