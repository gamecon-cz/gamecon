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
 * Archives cancelled items to CancelledOrderItem (shop_nakupy_zrusene) and tracks the
 * cancellation reason. Deleting the purchase row is what frees the stock.
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
     * @param int[] $purchaseIds
     *
     * @return int number of cancelled items
     */
    public function cancelPurchases(array $purchaseIds, string $reason, \DateTimeImmutable $cancelledAt): int
    {
        return $this->cancelItems($this->purchasesWithIds($purchaseIds), $reason, $cancelledAt);
    }

    /**
     * Takes purchases back without recording them as cancelled — a correction, not a cancellation.
     *
     * @param int[] $purchaseIds
     *
     * @return int number of removed items
     */
    public function removePurchases(array $purchaseIds): int
    {
        $items = $this->purchasesWithIds($purchaseIds);
        foreach ($items as $item) {
            $this->removeFromOrder($item);
            $this->entityManager->remove($item);
        }
        if ($items !== []) {
            $this->entityManager->flush();
        }

        return count($items);
    }

    /**
     * @param int[] $purchaseIds
     *
     * @return OrderItem[]
     */
    private function purchasesWithIds(array $purchaseIds): array
    {
        if ($purchaseIds === []) {
            return [];
        }

        return $this->orderItemRepository->findBy([
            'id' => array_map('intval', $purchaseIds),
        ], [
            'id' => 'ASC',
        ]);
    }

    /**
     * @param OrderItem[]|iterable $items
     *
     * @return int number of cancelled items
     */
    private function cancelItems(iterable $items, string $reason, \DateTimeImmutable $cancelledAt): int
    {
        $count = 0;

        foreach ($items as $item) {
            $this->archiveItem($item, $reason, $cancelledAt);
            $this->removeFromOrder($item);

            $this->entityManager->remove($item);
            ++$count;
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
        // The name as sold, so a later rename or a merge of products with the same code
        // cannot rewrite history. getDisplayName() keeps the variant, which is where the
        // size lives.
        $cancelled->setProductName($item->getDisplayName());
        $cancelled->setProductCode($item->getDisplayCode());

        $this->entityManager->persist($cancelled);
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
