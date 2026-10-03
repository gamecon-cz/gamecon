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
 * Sdílené schválně: strop musí odpovídat tomu, co pustí `CapacityManager::lockForSale()`
 * (včetně odečtu zásoby držené pro orgy). Kdyby to byly dvě kopie, změna pravidla by se
 * musela promítnout na obou místech a ta zapomenutá by selhala tiše — mřížka by nabídla
 * kus, který další klik odmítne, nebo naopak skryla kus, který by prošel.
 */
readonly class ProductVariantsForGrid
{
    public function __construct(
        private OrderItemRepository $orderItemRepository,
        private CapacityManager $capacityManager,
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
        $remaining = $this->capacityManager->remainingByVariant($product->getVariants()->toArray());
        foreach ($product->getVariants() as $variant) {
            $id = $variant->getId();
            // Varianta bez id není uložená, takže si ji zákazník nemá jak koupit.
            if ($id === null) {
                continue;
            }

            $purchased = $this->orderItemRepository->countCustomerPurchases($customer, $product, $year, $variant);
            // A size paused or withdrawn on its own is off sale, as legacy hid it; whoever holds it
            // still sees it, so it can be dropped, but cannot add more.
            $naProdej = ! $variant->isWithdrawn() && ! $variant->isPaused();
            if (! $naProdej && $purchased === 0) {
                continue;
            }

            $dto = new MerchVariantOutputDto();
            $dto->id = $id;
            $dto->name = $variant->getName();
            $dto->purchasedQuantity = $purchased;
            $dto->maxQuantity = $naProdej
                ? $this->maxQuantity($variant, $remaining[$id] ?? null, $purchased, $roleMeanings)
                : $purchased;
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
    private function maxQuantity(ProductVariant $variant, ?int $remaining, int $purchasedQuantity, array $roleMeanings): ?int
    {
        if ($remaining === null) {
            return null;
        }
        $available = $this->capacityManager->availableQuantity($variant, $roleMeanings, $remaining);

        return $available === null ? null : $available + $purchasedQuantity;
    }
}
