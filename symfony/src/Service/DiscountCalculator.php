<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Product;
use App\Entity\ProductDiscount;
use App\Entity\User;
use App\Enum\RoleMeaning;
use App\Repository\OrderItemRepository;
use App\Repository\ProductDiscountRepository;

/**
 * DiscountCalculator - calculates product discounts based on user roles
 *
 * Handles:
 * - Role-based discounts (from ProductDiscount entity)
 * - Quantity limits (maxQuantity)
 * - Best discount selection (when user has multiple roles)
 */
class DiscountCalculator
{
    public function __construct(
        private readonly ProductDiscountRepository $discountRepository,
        private readonly OrderItemRepository $orderItemRepository,
    ) {
    }

    /**
     * Calculate discount for product and user
     *
     * @return array{
     *     discount: ProductDiscount|null,
     *     discountAmount: string,
     *     finalPrice: string,
     *     reason: string|null
     * }
     */
    public function calculateDiscount(Product $product, User $user, int $year): array
    {
        $originalPrice = $product->getCurrentPrice();

        // Get user roles
        $userRoles = $this->getUserRoles($user);

        if ($userRoles === []) {
            return [
                'discount'       => null,
                'discountAmount' => '0.00',
                'finalPrice'     => $originalPrice,
                'reason'         => null,
            ];
        }

        // Find best discount for user's roles
        $bestDiscount = $this->discountRepository->findBestDiscountForProduct($product, $userRoles);

        if (! $bestDiscount instanceof ProductDiscount) {
            return [
                'discount'       => null,
                'discountAmount' => '0.00',
                'finalPrice'     => $originalPrice,
                'reason'         => null,
            ];
        }

        // Check quantity limit
        if ($bestDiscount->hasQuantityLimit()) {
            $alreadyPurchased = $this->orderItemRepository->countCustomerPurchases(
                $user,
                $product,
                $year
            );

            if ($alreadyPurchased >= $bestDiscount->getMaxQuantity()) {
                // Already used up discount quota
                return [
                    'discount'       => null,
                    'discountAmount' => '0.00',
                    'finalPrice'     => $originalPrice,
                    'reason'         => 'Limit slevy vyčerpán',
                ];
            }
        }

        // Calculate discount
        $discountAmount = $bestDiscount->calculateDiscountAmount($originalPrice);
        $finalPrice = $bestDiscount->calculateFinalPrice($originalPrice);
        $reason = $bestDiscount->getDescription();

        return [
            'discount'       => $bestDiscount,
            'discountAmount' => $discountAmount,
            'finalPrice'     => $finalPrice,
            'reason'         => $reason,
        ];
    }

    /**
     * Calculate discounts for multiple products
     *
     * @param Product[] $products
     *
     * @return array<int, array{discount: ProductDiscount|null, discountAmount: string, finalPrice: string, reason: string|null}>
     */
    public function calculateDiscountsForProducts(array $products, User $user, int $year): array
    {
        $results = [];
        foreach ($products as $product) {
            $results[$product->getId()] = $this->calculateDiscount($product, $user, $year);
        }

        return $results;
    }

    /**
     * Check if user is eligible for discount on product
     */
    public function isEligibleForDiscount(Product $product, User $user, int $year): bool
    {
        $result = $this->calculateDiscount($product, $user, $year);

        return $result['discount'] !== null;
    }

    /**
     * Get remaining discount quota for product
     */
    public function getRemainingQuota(Product $product, User $user, int $year): ?int
    {
        $userRoles = $this->getUserRoles($user);

        if ($userRoles === []) {
            return null;
        }

        $bestDiscount = $this->discountRepository->findBestDiscountForProduct($product, $userRoles);

        if (! $bestDiscount instanceof ProductDiscount || ! $bestDiscount->hasQuantityLimit()) {
            return null;
        }

        $alreadyPurchased = $this->orderItemRepository->countCustomerPurchases($user, $product, $year);
        $remaining = $bestDiscount->getMaxQuantity() - $alreadyPurchased;

        return max(0, $remaining);
    }

    /**
     * Get user role meanings for discount matching
     *
     * @return RoleMeaning[]
     */
    private function getUserRoles(User $user): array
    {
        return $user->getRoleMeanings();
    }
}
