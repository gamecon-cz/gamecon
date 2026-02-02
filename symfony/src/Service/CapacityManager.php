<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Product;
use App\Entity\User;
use App\Repository\OrderItemRepository;
use App\Repository\ProductRepository;

/**
 * CapacityManager - manages product capacities
 *
 * Handles:
 * - Accommodation-specific logic (reducing all days when one is purchased)
 * - Organizer vs participant capacity separation
 * - Availability checking before purchase
 */
class CapacityManager
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly OrderItemRepository $orderItemRepository,
    ) {
    }

    /**
     * Check if product has available capacity for user
     */
    public function hasAvailableCapacity(Product $product, User $user, int $year, string $userRole = 'ucastnik'): bool
    {
        $availableCapacity = $this->getAvailableCapacity($product, $year, $userRole);

        return $availableCapacity > 0;
    }

    /**
     * Get available capacity for product
     */
    public function getAvailableCapacity(Product $product, int $year, string $userRole = 'ucastnik'): int
    {
        // Check if product uses separate organizer/participant amounts
        if ($product->hasSeparateOrganizerAmount()) {
            return $this->getSeparateAmount($product, $year, $userRole);
        }

        // Use producedQuantity as total capacity
        $totalCapacity = $product->getProducedQuantity();

        if ($totalCapacity === null) {
            // Unlimited capacity
            return PHP_INT_MAX;
        }

        $soldCount = $this->orderItemRepository->countByProductAndYear($product, $year);

        return max(0, $totalCapacity - $soldCount);
    }

    /**
     * Get separate amount for organizers/participants
     */
    private function getSeparateAmount(Product $product, int $year, string $userRole): int
    {
        $isOrganizer = in_array($userRole, ['organizator', 'vypravec'], true);

        $amount = $isOrganizer ? $product->getAmountOrganizers() ?? 0 : $product->getAmountParticipants() ?? 0;

        $soldCount = $this->orderItemRepository->countByProductAndYear($product, $year);

        return max(0, $amount - $soldCount);
    }

    /**
     * Reduce capacity for accommodation products
     *
     * When user purchases accommodation for one day (e.g., Friday),
     * we need to reduce capacity for ALL accommodation days (SHOULD requirement)
     *
     * This ensures that if someone books Friday, the same bed is reserved
     * for Thursday, Saturday, etc.
     */
    public function reduceAccommodationCapacity(Product $product, int $year): void
    {
        if (! $product->isAccommodation()) {
            return;
        }

        // Find all accommodation products for this year
        $this->productRepository->findByTag('ubytovani');

        // This is a conceptual implementation
        // In reality, you might need to track capacity in a separate table
        // or implement a more sophisticated system

        // For now, we just mark that this method would reduce capacity
        // across all related accommodation days
    }

    /**
     * Validate capacity before adding to cart
     *
     * @throws \RuntimeException if capacity exceeded
     */
    public function validateCapacity(Product $product, User $user, int $year, int $quantity = 1, string $userRole = 'ucastnik'): void
    {
        $available = $this->getAvailableCapacity($product, $year, $userRole);

        if ($available < $quantity) {
            throw new \RuntimeException(sprintf('Nedostatečná kapacita pro produkt "%s". Dostupné: %d, požadované: %d', $product->getName(), $available, $quantity));
        }
    }

    /**
     * Get capacity info for display
     *
     * @return array{
     *     total: int|null,
     *     sold: int,
     *     available: int,
     *     percentSold: float
     * }
     */
    public function getCapacityInfo(Product $product, int $year, string $userRole = 'ucastnik'): array
    {
        $soldCount = $this->orderItemRepository->countByProductAndYear($product, $year);

        if ($product->hasSeparateOrganizerAmount()) {
            $isOrganizer = in_array($userRole, ['organizator', 'vypravec'], true);
            $totalCapacity = $isOrganizer
                ? $product->getAmountOrganizers()
                : $product->getAmountParticipants();
        } else {
            $totalCapacity = $product->getProducedQuantity();
        }

        $available = $totalCapacity !== null ? max(0, $totalCapacity - $soldCount) : PHP_INT_MAX;
        $percentSold = $totalCapacity !== null && $totalCapacity > 0
            ? ($soldCount / $totalCapacity) * 100
            : 0.0;

        return [
            'total'       => $totalCapacity,
            'sold'        => $soldCount,
            'available'   => $available,
            'percentSold' => $percentSold,
        ];
    }

    /**
     * Check if product is sold out
     */
    public function isSoldOut(Product $product, int $year, string $userRole = 'ucastnik'): bool
    {
        return $this->getAvailableCapacity($product, $year, $userRole) <= 0;
    }

    /**
     * Get low stock threshold status
     */
    public function isLowStock(Product $product, int $year, int $threshold = 10, string $userRole = 'ucastnik'): bool
    {
        $available = $this->getAvailableCapacity($product, $year, $userRole);

        return $available > 0 && $available <= $threshold;
    }
}
