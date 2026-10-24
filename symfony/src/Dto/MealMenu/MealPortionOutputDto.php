<?php

declare(strict_types=1);

namespace App\Dto\MealMenu;

/**
 * Jedna koupená porce a jídlo k ní.
 */
class MealPortionOutputDto
{
    public int $orderItemId;

    /**
     * Co si účastník vybral; NULL = zatím nic.
     */
    public ?int $chosenDishId = null;

    /**
     * Co porce opravdu dostane: vybrané jídlo, jinak výchozí. NULL, dokud slot nemá jídla.
     */
    public ?int $effectiveDishId = null;
}
