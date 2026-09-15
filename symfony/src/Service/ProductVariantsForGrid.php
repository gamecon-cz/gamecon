<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Cart\MerchVariantOutputDto;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\RoleMeaning;
use App\Repository\OrderItemRepository;

/**
 * Varianty produktu pro mřížku — merch i svršky je počítají stejně.
 *
 * Sdílené schválně: strop musí odpovídat tomu, co pustí `CapacityManager::purchase()`
 * (včetně odečtu zásoby držené pro orgy). Kdyby to byly dvě kopie, změna pravidla by se
 * musela promítnout na obou místech a ta zapomenutá by selhala tiše — mřížka by nabídla
 * kus, který další klik odmítne, nebo naopak skryla kus, který by prošel.
 */
readonly class ProductVariantsForGrid
{
    public function __construct(
        private OrderItemRepository $orderItemRepository,
    ) {
    }

    /**
     * @param RoleMeaning[] $roleMeanings
     *
     * @return MerchVariantOutputDto[]
     */
    public function pro(Product $product, User $customer, int $year, array $roleMeanings): array
    {
        $variants = [];
        foreach ($product->getVariants() as $variant) {
            $id = $variant->getId();
            // Varianta bez id není uložená, takže si ji zákazník nemá jak koupit.
            if ($id === null) {
                continue;
            }

            $purchased = $this->orderItemRepository->countCustomerPurchases($customer, $product, $year, $variant);

            $dto = new MerchVariantOutputDto();
            $dto->id = $id;
            $dto->name = $variant->getName();
            $dto->purchasedQuantity = $purchased;
            $dto->maxQuantity = $this->maxQuantity($variant, $purchased, $roleMeanings);
            $variants[] = $dto;
        }

        return $variants;
    }

    /**
     * Vlastní koupené kusy se přičítají zpátky: strop je „kolik jich smí mít", ne
     * „kolik jich smí ještě přikoupit".
     *
     * @param RoleMeaning[] $roleMeanings
     */
    private function maxQuantity(ProductVariant $variant, int $purchasedQuantity, array $roleMeanings): ?int
    {
        $remaining = $variant->getRemainingQuantity();
        if ($remaining === null) {
            return null;
        }

        if (! RoleMeaning::anyIsOrganizer($roleMeanings)) {
            $remaining -= $variant->getEffectiveReservedForOrganizers() ?? 0;
        }

        return max(0, $remaining) + $purchasedQuantity;
    }
}
