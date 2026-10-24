<?php

declare(strict_types=1);

namespace App\Dto\MealMenu;

/**
 * Jídelníček s porcemi jednoho zákazníka — pro výběr jídla v přihlášce, na webu i na pultu.
 */
class MealMenuOutputDto
{
    /**
     * Jídelníček je zveřejněný. Do té doby účastník jídla nevidí (pult ano).
     */
    public bool $published = false;

    /**
     * Datum zveřejnění (Y-m-d), aby šlo napsat, kdy si bude moct vybrat.
     */
    public string $publishedFrom;

    /**
     * Výběr je zavřený, protože skončil prodej jídla. Pultu se nezavírá nikdy.
     */
    public bool $locked = false;

    /**
     * Poslední den, kdy jde vybírat (Y-m-d).
     */
    public string $choiceDeadline;

    /**
     * @var MealMenuSlotOutputDto[]
     */
    public array $slots = [];
}
