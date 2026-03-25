<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ProductVariant;
use App\Enum\RoleMeaning;
use Doctrine\DBAL\Connection;

/**
 * CapacityManager - manages variant stock with atomic DB operations
 *
 * Uses atomic UPDATE with WHERE condition to prevent overselling.
 * No need for explicit locks — the UPDATE itself is atomic in InnoDB.
 *
 * Stock (remaining_quantity) lives on ProductVariant only.
 * reserved_for_organizers on variant inherits from parent Product if null.
 */
class CapacityManager
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * Check if variant has available capacity for given roles
     *
     * @param RoleMeaning[] $roleMeanings
     */
    public function hasAvailableCapacity(ProductVariant $variant, array $roleMeanings = []): bool
    {
        $available = $variant->getAvailableQuantity($roleMeanings);

        return $available === null || $available > 0;
    }

    /**
     * Atomically purchase variant — decrements remaining_quantity.
     *
     * @param RoleMeaning[] $roleMeanings
     *
     * @throws \RuntimeException if not enough stock
     */
    public function purchase(ProductVariant $variant, int $quantity = 1, array $roleMeanings = []): void
    {
        if ($variant->getRemainingQuantity() === null) {
            return; // unlimited capacity, nothing to decrement
        }

        $isOrganizer = RoleMeaning::anyIsOrganizer($roleMeanings);

        if ($isOrganizer) {
            $affectedRows = $this->connection->executeStatement(
                'UPDATE product_variant
                SET remaining_quantity = remaining_quantity - :qty
                WHERE id = :id
                AND remaining_quantity >= :qty',
                [
                    'qty' => $quantity,
                    'id'  => $variant->getId(),
                ],
            );
        } else {
            // Participants cannot buy into organizer-reserved stock.
            // reserved_for_organizers may be on variant or inherited from product (via subquery).
            $affectedRows = $this->connection->executeStatement(
                'UPDATE product_variant
                SET remaining_quantity = remaining_quantity - :qty
                WHERE id = :id
                AND remaining_quantity - COALESCE(
                    reserved_for_organizers,
                    (SELECT reserved_for_organizers FROM shop_predmety WHERE id_predmetu = product_id),
                    0
                ) >= :qty',
                [
                    'qty' => $quantity,
                    'id'  => $variant->getId(),
                ],
            );
        }

        if ($affectedRows === 0) {
            throw new \RuntimeException(sprintf('Nedostatečná kapacita pro produkt "%s". Požadované: %d', $variant->getFullName(), $quantity));
        }

        // Sync entity state with DB
        $variant->setRemainingQuantity($variant->getRemainingQuantity() - $quantity);
    }

    /**
     * Atomically return stock when a purchase is cancelled.
     */
    public function cancelPurchase(ProductVariant $variant, int $quantity = 1): void
    {
        if ($variant->getRemainingQuantity() === null) {
            return; // unlimited capacity
        }

        $this->connection->executeStatement(
            'UPDATE product_variant
            SET remaining_quantity = remaining_quantity + :qty
            WHERE id = :id',
            [
                'qty' => $quantity,
                'id'  => $variant->getId(),
            ],
        );

        // Sync entity state
        $variant->setRemainingQuantity($variant->getRemainingQuantity() + $quantity);
    }

    /**
     * Check if variant is sold out for given roles
     *
     * @param RoleMeaning[] $roleMeanings
     */
    public function isSoldOut(ProductVariant $variant, array $roleMeanings = []): bool
    {
        return ! $this->hasAvailableCapacity($variant, $roleMeanings);
    }

    /**
     * Check if variant is low on stock
     *
     * @param RoleMeaning[] $roleMeanings
     */
    public function isLowStock(ProductVariant $variant, int $threshold = 10, array $roleMeanings = []): bool
    {
        $available = $variant->getAvailableQuantity($roleMeanings);

        if ($available === null) {
            return false; // unlimited
        }

        return $available > 0 && $available <= $threshold;
    }

    /**
     * Get capacity info for display
     *
     * @return array{
     *     remaining: int|null,
     *     reserved: int,
     *     availableForParticipants: int|null,
     *     unlimited: bool
     * }
     */
    public function getCapacityInfo(ProductVariant $variant): array
    {
        $remaining = $variant->getRemainingQuantity();
        $reserved = $variant->getEffectiveReservedForOrganizers() ?? 0;

        return [
            'remaining'                => $remaining,
            'reserved'                 => $reserved,
            'availableForParticipants' => $remaining !== null ? max(0, $remaining - $reserved) : null,
            'unlimited'                => $remaining === null,
        ];
    }
}
