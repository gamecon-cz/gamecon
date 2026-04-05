<?php

declare(strict_types=1);

namespace App\Dto\Kfc;

use Symfony\Component\Validator\Constraints as Assert;

class KfcSaleInputDto
{
    /**
     * @var KfcSaleItemInputDto[]
     */
    #[Assert\NotBlank(message: 'Musí být zadána alespoň jedna položka')]
    #[Assert\Valid]
    public array $items = [];
}
