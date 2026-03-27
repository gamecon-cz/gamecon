<?php

declare(strict_types=1);

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class BulkCancelInputDto
{
    /**
     * @var int[]
     */
    #[Assert\NotBlank(message: 'Musí být zadán alespoň jeden uživatel')]
    #[Assert\All([
        new Assert\Positive(),
    ])]
    public array $userIds = [];

    /**
     * Product tag to filter by (e.g. 'ubytovani', 'jidlo'). Null = cancel all.
     */
    public ?string $tag = null;

    #[Assert\NotBlank(message: 'Důvod zrušení musí být vyplněn')]
    public ?string $reason = null;
}
