<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\OrderItem;
use App\Service\BreakfastCanceller;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

/**
 * Accommodation capacity across days is handled by the bundle system (buying a bundle of day
 * variants), not by cross-decrementing siblings.
 */
#[AsEntityListener(event: Events::postPersist, entity: OrderItem::class)]
readonly class OrderItemCreatedListener
{
    public function __construct(
        private BreakfastCanceller $breakfastCanceller,
    ) {
    }

    public function postPersist(OrderItem $orderItem): void
    {
        // Buying a breakfast restates what the customer wants, so the selection a hotel night
        // would later offer back has to follow it. Accommodation's own writer inserts in SQL
        // and never reaches this, which keeps it to genuine meal purchases.
        $this->breakfastCanceller->refreshSnapshot($orderItem);
    }
}
