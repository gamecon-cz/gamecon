<?php

declare(strict_types=1);

namespace App\Dto\MealMenu;

/**
 * Celý letošní jídelníček pro editor v adminu, s počty, kolik lidí si které jídlo vybralo.
 */
class AdminMealMenuOutputDto
{
    public bool $published = false;

    /**
     * Y-m-d
     */
    public string $publishedFrom;

    public bool $choiceClosed = false;

    /**
     * Y-m-d
     */
    public string $choiceDeadline;

    /**
     * @var AdminMealMenuSlotOutputDto[]
     */
    public array $slots = [];
}
