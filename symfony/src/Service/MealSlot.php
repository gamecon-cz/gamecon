<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\MealDish;
use App\Entity\OrderItem;
use App\Entity\ProductVariant;

/**
 * Jeden oběd nebo večeře v jídelníčku: porce, kterou jde koupit, a jídla, ze kterých se k ní
 * vybírá. Snídaně sloty nejsou, je to švédský stůl.
 */
readonly class MealSlot
{
    /**
     * @param MealDish[] $dishes v pořadí jídelníčku
     */
    public function __construct(
        public ProductVariant $variant,
        public int $day,
        public string $name,
        public array $dishes,
    ) {
    }

    public function getVariantId(): int
    {
        return $this->variant->getId() ?? throw new \LogicException('Slot jídelníčku stojí na uložené variantě.');
    }

    /**
     * Pořadí druhu jídla během dne podle názvu produktu („Oběd pátek"). Jinak než z názvu se
     * oběd od večeře nepozná; snídaně má i štítek.
     */
    public static function getMealOrder(string $productName): int
    {
        return match (mb_strtolower(explode(' ', trim($productName))[0])) {
            'snídaně' => 1,
            'oběd'    => 2,
            'večeře'  => 3,
            default   => 4,
        };
    }

    public function getDayName(): string
    {
        return ProductVariant::NIGHT_NAMES[$this->day] ?? (string) $this->day;
    }

    /**
     * Co dostane, kdo si nevybere. Když výchozí nikdo neoznačil (editor to nedovolí, ale
     * jídla mohla vzniknout i jinak), je jím první jídlo.
     */
    public function getDefaultDish(): ?MealDish
    {
        foreach ($this->dishes as $dish) {
            if ($dish->isDefault()) {
                return $dish;
            }
        }

        return $this->dishes[0] ?? null;
    }

    /**
     * Jídlo, které porce opravdu dostane: vybrané, jinak výchozí.
     */
    public function getEffectiveDish(OrderItem $portion): ?MealDish
    {
        $chosen = $portion->getMealDish();
        if ($chosen !== null && $this->hasDish($chosen)) {
            return $chosen;
        }

        return $this->getDefaultDish();
    }

    public function findDish(int $dishId): ?MealDish
    {
        foreach ($this->dishes as $dish) {
            if ($dish->getId() === $dishId) {
                return $dish;
            }
        }

        return null;
    }

    private function hasDish(MealDish $dish): bool
    {
        return $dish->getId() !== null && $this->findDish($dish->getId()) !== null;
    }
}
