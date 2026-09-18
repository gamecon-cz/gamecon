<?php

declare(strict_types=1);

namespace App\Dto\Cart;

use App\Entity\Product;
use App\Entity\ProductVariant;

/**
 * Flat DTO for meal products — contains only what the meal matrix UI needs.
 * Decoupled from Product entity serialization groups.
 */
class MealProductOutputDto
{
    public string $name;
    public int $day;
    public string $price;
    public int $variantId;
    public ?int $remainingQuantity;

    /**
     * Cena podle pořadí kusu; vždy aspoň jeden stupeň. U jídla dnes žádné pravidlo
     * s omezeným počtem není, takže je stupeň jeden — ale tvar je stejný jako u merche.
     *
     * @var array<int, array{fromQuantity: int, price: string, discountAmount: string, ruleCode: string|null, ruleName: string|null, label: string|null}>
     */
    public array $priceSteps = [];

    public static function fromProductAndVariant(Product $product, ProductVariant $variant): self
    {
        $dto = new self();
        $dto->name = $product->getName();
        $dto->day = $variant->getAccommodationDay() ?? $product->getAccommodationDay() ?? 0;
        $dto->price = $variant->getEffectivePrice();
        $dto->variantId = $variant->getId();
        // Zásoba smí být záporná (admin prodává i nad kapacitu), ale „zbývá −2" nemá ve
        // frontendu význam. Mřížka merche clampuje stejně, viz ProductVariantsForGrid.
        $zbyva = $variant->getRemainingQuantity();
        $dto->remainingQuantity = $zbyva === null ? null : max(0, $zbyva);

        return $dto;
    }
}
