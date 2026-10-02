<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Exception\CapacityExceededException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * A sale entered by staff in the legacy admin. It charges the list price without discounts and
 * may sell past deadlines and into the organizer reserve; only the capacity itself holds.
 */
class ManualSaleService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CapacityManager $capacityManager,
    ) {
    }

    /**
     * @return Order the completed order holding one row per piece
     *
     * @throws CapacityExceededException
     */
    public function sell(
        User $customer,
        User $orderer,
        ProductVariant $variant,
        int $pieces,
        string $unitPrice,
        int $year,
    ): Order {
        $connection = $this->entityManager->getConnection();
        $this->capacityManager->beginSaleTransaction();
        try {
            // Before anything is persisted: a refusal must leave nothing for a later flush().
            $this->capacityManager->lockForSale($variant, $pieces, override: OperatorOverride::deskSale($orderer));

            $product = $variant->getProduct();
            $order = new Order();
            $order->setCustomer($customer);
            $order->setYear($year);
            for ($piece = 0; $piece < $pieces; ++$piece) {
                $item = new OrderItem();
                $item->setCustomer($customer);
                $item->setOrderer($orderer);
                $item->setProduct($product);
                $item->setVariant($variant);
                $item->setYear($year);
                $item->setOrder($order);
                $item->setPurchasePrice($unitPrice);
                $item->snapshotProduct($product, $variant);
                $item->setProductTags($product->getTagNames());
                $item->setDiscountAmount('0.00');
                $order->addItem($item);
            }
            $order->recalculateTotal();
            $order->complete();

            $this->entityManager->persist($order);
            foreach ($order->getItems() as $item) {
                $this->entityManager->persist($item);
            }
            $this->entityManager->flush();
            $connection->commit();

            return $order;
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }
    }
}
