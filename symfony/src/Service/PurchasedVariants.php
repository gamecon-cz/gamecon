<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\CancelledOrderItemRepository;
use App\Repository\OrderItemRepository;

/**
 * A variant counts as bought even when its purchase was cancelled since: the cancelled purchase
 * keeps pointing at it, so it cannot be deleted either.
 */
class PurchasedVariants
{
    public function __construct(
        private readonly OrderItemRepository $orderItemRepository,
        private readonly CancelledOrderItemRepository $cancelledOrderItemRepository,
    ) {
    }

    /**
     * @param int[] $variantIds
     *
     * @return int[] those of them that were ever bought
     */
    public function among(array $variantIds): array
    {
        return array_values(array_unique([
            ...$this->orderItemRepository->soldVariantIds($variantIds),
            ...$this->cancelledOrderItemRepository->cancelledVariantIds($variantIds),
        ]));
    }
}
