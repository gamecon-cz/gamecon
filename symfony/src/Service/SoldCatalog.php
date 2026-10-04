<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Repository\CancelledOrderItemRepository;
use App\Repository\OrderItemRepository;
use Symfony\Contracts\Service\ResetInterface;

/**
 * A cancelled purchase keeps pointing at its variant too, so it counts as sold. Loaded once for
 * the whole catalog, as the product admin asks about every product it lists.
 */
class SoldCatalog implements ResetInterface
{
    /**
     * @var array<int, true>
     */
    private array $soldVariantIds = [];

    /**
     * @var array<string, true>
     */
    private array $soldVariantCodes = [];

    private bool $loaded = false;

    public function __construct(
        private readonly OrderItemRepository $orderItemRepository,
        private readonly CancelledOrderItemRepository $cancelledOrderItemRepository,
    ) {
    }

    public function isVariantSold(ProductVariant $variant): bool
    {
        $this->load();

        return $variant->getId() !== null && isset($this->soldVariantIds[$variant->getId()]);
    }

    public function isProductSold(Product $product): bool
    {
        foreach ($product->getVariants() as $variant) {
            if ($this->isVariantSold($variant)) {
                return true;
            }
        }
        $this->load();

        // A size's or night's own catalog row from the legacy layout has no variants of its own;
        // the item it stands for is the variant with its code, whose purchases point at the model.
        return isset($this->soldVariantCodes[$product->getCode()]);
    }

    public function reset(): void
    {
        $this->soldVariantIds = [];
        $this->soldVariantCodes = [];
        $this->loaded = false;
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }
        $codes = $this->orderItemRepository->everSoldVariantCodes()
            + $this->cancelledOrderItemRepository->everCancelledVariantCodes();
        $this->soldVariantIds = array_fill_keys(array_keys($codes), true);
        $this->soldVariantCodes = array_fill_keys(array_values($codes), true);
        $this->loaded = true;
    }
}
