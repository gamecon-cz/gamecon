<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\ProductDiscount;
use App\Entity\User;
use App\Repository\OrderRepository;
use App\Service\DiscountCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * UserRoleChangedListener - recalculates discounts when user role changes
 *
 * MUST requirement: Rekalkulace při změně role
 *
 * Use case:
 * - User upgrades from 'ucastnik' to 'organizator'
 * - Active cart/pending orders need discount recalculation
 * - Completed orders preserve original prices (purchasePrice is frozen)
 *
 * Triggered by:
 * - User role assignment/removal
 * - Custom event dispatch when roles change
 */
readonly class UserRoleChangedListener
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OrderRepository $orderRepository,
        private DiscountCalculator $discountCalculator,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Handle user role change event
     *
     * This should be called from UserRole management code
     * when a user's role is added/removed/changed
     */
    public function onUserRoleChanged(User $user, int $year): void
    {
        $this->logger->info('User role changed, recalculating discounts', [
            'user_id' => $user->getId(),
            'year'    => $year,
        ]);

        // Find pending order (cart)
        $pendingOrder = $this->orderRepository->findPendingForCustomer($user, $year);

        if ($pendingOrder instanceof Order) {
            $this->recalculateOrderDiscounts($pendingOrder, $user, $year);
        }
    }

    /**
     * Recalculate discounts for all items in order
     */
    private function recalculateOrderDiscounts(Order $order, User $user, int $year): void
    {
        $hasChanges = false;

        foreach ($order->getItems() as $orderItem) {
            $product = $orderItem->getProduct();

            if ($product === null) {
                continue;
            }

            // Calculate new discount based on current role
            $discountInfo = $this->discountCalculator->calculateDiscount($product, $user, $year);

            // Update order item with new pricing
            if ($this->updateOrderItemPricing($orderItem, $discountInfo)) {
                $hasChanges = true;
            }
        }

        if ($hasChanges) {
            // Recalculate order total
            $order->recalculateTotal();

            $this->entityManager->flush();

            $this->logger->info('Order discounts recalculated', [
                'order_id'  => $order->getId(),
                'new_total' => $order->getTotalPrice(),
            ]);
        }
    }

    /**
     * Update order item pricing with new discount
     *
     * @param array{discount: ProductDiscount|null, discountAmount: string, finalPrice: string, reason: string|null} $discountInfo
     *
     * @return bool True if changed
     */
    private function updateOrderItemPricing(OrderItem $orderItem, array $discountInfo): bool
    {
        $product = $orderItem->getProduct();

        if (! $product instanceof Product) {
            return false;
        }

        $newPurchasePrice = $discountInfo['finalPrice'];
        $newDiscountAmount = $discountInfo['discountAmount'];
        $newDiscountReason = $discountInfo['reason'];

        // Check if pricing changed
        if (
            $orderItem->getPurchasePrice() === $newPurchasePrice
            && $orderItem->getDiscountAmount() === $newDiscountAmount
        ) {
            return false;
        }

        // Update pricing
        $orderItem->setOriginalPrice($product->getCurrentPrice());
        $orderItem->setPurchasePrice($newPurchasePrice);
        $orderItem->setDiscountAmount($newDiscountAmount);
        $orderItem->setDiscountReason($newDiscountReason);

        $this->logger->debug('OrderItem pricing updated', [
            'order_item_id'   => $orderItem->getId(),
            'product_name'    => $product->getName(),
            'old_price'       => $orderItem->getPurchasePrice(),
            'new_price'       => $newPurchasePrice,
            'discount_amount' => $newDiscountAmount,
        ]);

        return true;
    }

    /**
     * Force recalculation for completed orders (COULD requirement - násilná rekalkulace)
     *
     * This overrides the frozen prices in completed orders
     * Use with caution - only for admin-initiated corrections
     */
    public function forceRecalculateCompletedOrder(Order $order, User $user, int $year): void
    {
        if (! $order->isCompleted()) {
            throw new \InvalidArgumentException('Order is not completed');
        }

        $this->logger->warning('FORCE recalculation of completed order', [
            'order_id' => $order->getId(),
            'user_id'  => $user->getId(),
        ]);

        $this->recalculateOrderDiscounts($order, $user, $year);
    }
}
