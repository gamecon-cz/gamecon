<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CancelledOrderItem;
use App\Entity\OrderItem;
use App\Repository\OrderItemRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Cancels purchases of the e-shop and archives them to CancelledOrderItem (shop_nakupy_zrusene)
 * with the cancellation reason. Deleting the purchase row is what frees the stock.
 */
class BulkCancelService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OrderItemRepository $orderItemRepository,
    ) {
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
        $cancelled->setVariant($item->getVariant() ?? throw new \LogicException('Zrušený nákup nemá variantu.'));
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
