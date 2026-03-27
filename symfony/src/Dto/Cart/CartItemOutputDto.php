<?php

declare(strict_types=1);

namespace App\Dto\Cart;

use App\Entity\OrderItem;

class CartItemOutputDto
{
    public ?int $id;
    public string $productName;
    public ?string $productCode;
    public ?string $variantName;
    public ?string $variantCode;
    public string $purchasePrice;
    public ?string $originalPrice;
    public ?string $discountAmount;
    public ?string $discountReason;
    public ?int $bundleId;

    public static function fromOrderItem(OrderItem $item): self
    {
        $dto = new self();
        $dto->id = $item->getId();
        $dto->productName = $item->getDisplayName();
        $dto->productCode = $item->getDisplayCode();
        $dto->variantName = $item->getVariantName();
        $dto->variantCode = $item->getVariantCode();
        $dto->purchasePrice = $item->getPurchasePrice();
        $dto->originalPrice = $item->getOriginalPrice();
        $dto->discountAmount = $item->getDiscountAmount();
        $dto->discountReason = $item->getDiscountReason();
        $dto->bundleId = $item->getBundle()?->getId();

        return $dto;
    }
}
