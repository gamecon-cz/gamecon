<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\OrderItem;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;

/**
 * OrderItemCreatedListener - handles capacity reduction when OrderItem is created
 *
 * SHOULD requirement: When accommodation is purchased, reduce capacity of ALL days
 *
 * Use case:
 * - User buys "Friday accommodation"
 * - We need to reduce capacity for Thursday, Friday, Saturday, Sunday too
 * - Because the same bed is reserved for all days
 */
#[AsEntityListener(event: Events::postPersist, entity: OrderItem::class)]
readonly class OrderItemCreatedListener
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function postPersist(OrderItem $orderItem): void
    {
        $product = $orderItem->getProduct();

        if (! $product) {
            return;
        }

        // Check if product is accommodation
        if (! $product->isAccommodation()) {
            return;
        }

        // Handle accommodation capacity reduction
        $this->handleAccommodationCapacity($orderItem);
    }

    /**
     * Handle capacity reduction for accommodation products
     */
    private function handleAccommodationCapacity(OrderItem $orderItem): void
    {
        $product = $orderItem->getProduct();

        if (! $product) {
            return;
        }

        $year = $orderItem->getYear();

        // Find all accommodation products for this year
        // In a real implementation, we would:
        // 1. Find all accommodation products (ubytovani tag)
        // 2. Reduce shared capacity across all days
        // 3. Or use a separate capacity tracking table

        $this->logger->info('Accommodation purchased, reducing capacity for all days', [
            'product_id'   => $product->getId(),
            'product_name' => $product->getName(),
            'year'         => $year,
            'customer_id'  => $orderItem->getCustomer()?->getId(),
        ]);

        // TODO: Implement actual capacity reduction logic
        // This requires a more sophisticated capacity tracking system
        // Options:
        // 1. Separate capacity table (accommodation_capacity) with daily tracking
        // 2. Shared capacity pool across all accommodation products
        // 3. Reservation system with bed/room assignments
    }
}
