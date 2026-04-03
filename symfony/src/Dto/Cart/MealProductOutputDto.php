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

    public static function fromProductAndVariant(Product $product, ProductVariant $variant): self
    {
        $dto = new self();
        $dto->name = $product->getName();
        $dto->day = $variant->getAccommodationDay() ?? $product->getAccommodationDay() ?? 0;
        $dto->price = $variant->getEffectivePrice();
        $dto->variantId = $variant->getId();
        $dto->remainingQuantity = $variant->getRemainingQuantity();

        return $dto;
    }
}
