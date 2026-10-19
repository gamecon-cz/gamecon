<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\OrderItemRepository;
use App\Structure\Sql\OrderItemLogSqlStructure;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Hands the purchases of a user who is being merged into another one over to that other user.
 * Goes through the entity manager so the purchase log records the move; a raw UPDATE would not.
 */
class PurchaseTransferService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OrderItemRepository $orderItemRepository,
    ) {
    }

    public function transferAll(int $fromUserId, int $toUserId): void
    {
        $from = $this->entityManager->getReference(User::class, $fromUserId);
        $to = $this->entityManager->getReference(User::class, $toUserId);

        foreach ($this->orderItemRepository->findByCustomerOrOrderer($from) as $purchase) {
            if ($purchase->getCustomer() === $from) {
                $purchase->setCustomer($to);
            }
            if ($purchase->getOrderer() === $from) {
                $purchase->setOrderer($to);
            }
        }
        $this->entityManager->flush();

        // The log has no foreign keys, so the merge does not carry it over: without this the
        // history of the moved purchases would stay under a user that no longer exists.
        $connection = $this->entityManager->getConnection();
        $connection->update(OrderItemLogSqlStructure::_table, [
            OrderItemLogSqlStructure::customer_id => $toUserId,
        ], [
            OrderItemLogSqlStructure::customer_id => $fromUserId,
        ]);
        $connection->update(OrderItemLogSqlStructure::_table, [
            OrderItemLogSqlStructure::changed_by_id => $toUserId,
        ], [
            OrderItemLogSqlStructure::changed_by_id => $fromUserId,
        ]);
    }
}
