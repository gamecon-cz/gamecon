<?php

declare(strict_types=1);

namespace App\Dto\Admin;

/**
 * The meals a participant holds. Only the selection: the catalogue comes from /cart/meals,
 * which is the same for everyone.
 */
class CustomerMealsOutputDto
{
    /**
     * @var int[]
     */
    public array $variantIds = [];
}
