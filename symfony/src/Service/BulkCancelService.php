<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CancelledOrderItem;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use App\Repository\OrderItemRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * BulkCancelService — handles bulk cancellation of e-shop orders/items.
 *
 * Archives cancelled items to CancelledOrderItem (shop_nakupy_zrusene),
 * returns stock via CapacityManager, and tracks cancellation reason.
 *
 * Use cases:
 * - Cancel all purchases for a non-paying user
 * - Cancel all accommodation for a user (tag = 'ubytovani')
 * - Cancel accommodation for multiple non-payers at once
 * - Cancel entire order
 */
class BulkCancelService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OrderItemRepository $orderItemRepository,
        private readonly CapacityManager $capacityManager,
        private readonly CurrentYearProviderInterface $currentYearProvider,
    ) {
    }

    /**
     * Cancel all items for a user in the current year.
     *
     * @return int number of cancelled items
     */
    public function cancelAllForUser(
        User $user,
        string $reason,
        \DateTimeImmutable $cancelledAt,
    ): int {
        $year = $this->currentYearProvider->getCurrentYear();
        $items = $this->orderItemRepository->findByCustomerAndYear($user, $year);

        return $this->cancelItems($items, $reason, $cancelledAt);
    }

    /**
     * Cancel items with a specific product tag for a user in the current year.
     *
     * @return int number of cancelled items
     */
    public function cancelByTagForUser(
        User $user,
        string $tag,
        string $reason,
        \DateTimeImmutable $cancelledAt,
    ): int {
        $year = $this->currentYearProvider->getCurrentYear();
        $items = $this->orderItemRepository->findByCustomerAndYear($user, $year);

        $filtered = array_filter(
            $items,
            static fn (OrderItem $item): bool => in_array($tag, $item->getProductTags(), true),
        );

        return $this->cancelItems($filtered, $reason, $cancelledAt);
    }

    /**
     * Cancel items with a specific product tag for multiple users.
     *
     * @param User[] $users
     *
     * @return int total number of cancelled items across all users
     */
    public function cancelByTagForUsers(
        array $users,
        string $tag,
        string $reason,
        \DateTimeImmutable $cancelledAt,
    ): int {
        $total = 0;
        foreach ($users as $user) {
            $total += $this->cancelByTagForUser($user, $tag, $reason, $cancelledAt);
        }

        return $total;
    }

    /**
     * Cancel all items in an order.
     *
     * @return int number of cancelled items
     */
    public function cancelOrder(
        Order $order,
        string $reason,
        \DateTimeImmutable $cancelledAt,
    ): int {
        $items = $order->getItems()->toArray();
        $count = $this->cancelItems($items, $reason, $cancelledAt);

        $order->cancel();
        $this->entityManager->flush();

        return $count;
    }

    /**
     * Cancel a list of OrderItems: archive, return stock, remove.
     *
     * @param OrderItem[]|iterable $items
     *
     * @return int number of cancelled items
     */
    private function cancelItems(iterable $items, string $reason, \DateTimeImmutable $cancelledAt): int
    {
        $count = 0;

        foreach ($items as $item) {
            $this->archiveItem($item, $reason, $cancelledAt);
            $this->returnStock($item);
            $this->removeFromOrder($item);

            $this->entityManager->remove($item);
            $count++;
        }

        if ($count > 0) {
            $this->entityManager->flush();
        }

        return $count;
    }

    private function archiveItem(OrderItem $item, string $reason, \DateTimeImmutable $cancelledAt): void
    {
        $cancelled = new CancelledOrderItem();

        if ($item->getId() !== null) {
            $cancelled->setId($item->getId());
        }

        $cancelled->setCustomer($item->getCustomer());
        $cancelled->setProduct($item->getProduct());
        $cancelled->setYear($item->getYear());
        $cancelled->setPurchasePrice($item->getPurchasePrice());
        $cancelled->setPurchasedAt($item->getPurchasedAt());
        $cancelled->setCancelledAt($cancelledAt);
        $cancelled->setCancellationReason($reason);

        $this->entityManager->persist($cancelled);
    }

    private function returnStock(OrderItem $item): void
    {
        $variant = $item->getVariant();

        if ($variant !== null) {
            $this->capacityManager->cancelPurchase($variant);
        }
    }

    private function removeFromOrder(OrderItem $item): void
    {
        $order = $item->getOrder();

        if ($order !== null) {
            $order->removeItem($item);
            $order->recalculateTotal();
        }
    }
}
