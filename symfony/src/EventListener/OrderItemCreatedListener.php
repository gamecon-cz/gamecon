<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\OrderItem;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

/**
 * Placeholder for future OrderItem lifecycle hooks.
 *
 * Accommodation capacity across days is handled by the bundle system
 * (buying a bundle of day variants), not by cross-decrementing siblings.
 */
#[AsEntityListener(event: Events::postPersist, entity: OrderItem::class)]
readonly class OrderItemCreatedListener
{
    public function postPersist(OrderItem $orderItem): void
    {
    }
}
