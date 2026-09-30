<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ProductVariant;
use App\Enum\RoleMeaning;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Remaining stock is the variant's capacity minus this year's purchase rows — nothing stores it,
 * so no write can leave it stale. Cancelling a purchase removes its row, which is the whole refund.
 */
class CapacityManager
{
    public function __construct(
        private readonly Connection $connection,
        private readonly CurrentYearProviderInterface $currentYearProvider,
    ) {
    }

    /**
     * Null means unlimited. Negative when the desk sold over capacity.
     */
    public function remaining(ProductVariant $variant): ?int
    {
        return $this->remainingByVariant([$variant])[(int) $variant->getId()] ?? null;
    }

    /**
     * @param ProductVariant[] $variants
     *
     * @return array<int, int|null> keyed by variant id; null = unlimited
     */
    public function remainingByVariant(array $variants): array
    {
        $variantIds = [];
        foreach ($variants as $variant) {
            if ($variant->getId() !== null) {
                $variantIds[] = $variant->getId();
            }
        }

        return $this->remainingByVariantId($variantIds);
    }

    /**
     * @param int[] $variantIds
     *
     * @return array<int, int|null> keyed by variant id; null = unlimited
     */
    public function remainingByVariantId(array $variantIds): array
    {
        if ($variantIds === []) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT product_variant.id,
                    product_variant.capacity AS kapacita,
                    (
                        SELECT COUNT(*)
                        FROM shop_nakupy
                        WHERE shop_nakupy.rok = :rok
                          AND shop_nakupy.variant_id = product_variant.id
                    ) AS prodano
             FROM product_variant
             WHERE product_variant.id IN (:ids)',
            [
                'rok' => $this->currentYearProvider->getCurrentYear(),
                'ids' => $variantIds,
            ],
            [
                'ids' => ArrayParameterType::INTEGER,
            ],
        );

        $remaining = [];
        foreach ($rows as $row) {
            $remaining[(int) $row['id']] = $row['kapacita'] === null
                ? null
                : (int) $row['kapacita'] - (int) $row['prodano'];
        }

        return $remaining;
    }

    /**
     * What the buyer may still take: participants cannot reach the part held back for
     * organizers. Never negative.
     *
     * @param RoleMeaning[] $roleMeanings
     */
    public function availableQuantity(ProductVariant $variant, array $roleMeanings = [], ?int $remaining = null): ?int
    {
        $remaining ??= $this->remaining($variant);
        if ($remaining === null) {
            return null;
        }

        return max(0, $remaining - $this->heldBackFrom($variant, $roleMeanings));
    }

    /**
     * READ COMMITTED, because under REPEATABLE READ the stock count also locks index gaps and
     * buyers of two different products then deadlock on each other's insert. `SET TRANSACTION`
     * covers only the next transaction; a nested call joins the outer one.
     */
    public function beginSaleTransaction(): void
    {
        if (! $this->connection->isTransactionActive()) {
            $this->connection->executeStatement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        }
        $this->connection->beginTransaction();
    }

    /**
     * Takes every capacity lock a sale will need before its first write, lowest id first: two
     * sales taking the same rows in different orders, or one deleting a purchase the other then
     * counts, deadlock. Include what the sale removes, not only what it adds.
     *
     * @param int[] $variantIds
     *
     * @throws \LogicException outside a transaction, where the locks would end at once
     */
    public function lockInOrder(array $variantIds): void
    {
        if (! $this->connection->isTransactionActive()) {
            throw new \LogicException('Kapacitu jde zamknout jen v transakci, která nákup i zapíše.');
        }

        $variantIds = array_values(array_unique(array_map('intval', $variantIds)));
        sort($variantIds);
        // One row per statement: a single `IN (…)` leaves the lock order to the query plan.
        foreach ($variantIds as $variantId) {
            $this->connection->fetchOne('SELECT id FROM product_variant WHERE id = :id FOR UPDATE', [
                'id' => $variantId,
            ]);
        }
    }

    /**
     * Locks the variant's capacity row until the surrounding transaction ends, so the sale
     * must be written in that same transaction. The count is a locking read: a plain one would
     * reuse the caller's REPEATABLE READ snapshot and miss a purchase committed while it waited.
     *
     * @param RoleMeaning[] $roleMeanings
     *
     * @throws \RuntimeException if not enough stock
     * @throws \LogicException   outside a transaction, where the lock would end at once
     */
    public function lockForSale(ProductVariant $variant, int $quantity = 1, array $roleMeanings = [], ?OperatorOverride $override = null): void
    {
        if (! $this->connection->isTransactionActive()) {
            throw new \LogicException('Kapacitu jde hlídat jen v transakci, která nákup i zapíše.');
        }

        $capacity = $this->connection->fetchOne(
            'SELECT capacity FROM product_variant WHERE id = :id FOR UPDATE',
            [
                'id' => $variant->getId(),
            ],
        );
        if ($capacity === null || $capacity === false) {
            return;
        }

        $sold = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM shop_nakupy WHERE shop_nakupy.rok = :rok AND shop_nakupy.variant_id = :id LOCK IN SHARE MODE',
            [
                'rok' => $this->currentYearProvider->getCurrentYear(),
                'id'  => $variant->getId(),
            ],
        );

        $heldBack = $override?->allows(OperatorOverride::GUARD_ORGANIZER_STOCK) === true
            ? 0
            : $this->heldBackFrom($variant, $roleMeanings);

        if ((int) $capacity - $heldBack - $sold < $quantity) {
            throw new \RuntimeException(sprintf('Nedostatečná kapacita pro produkt "%s". Požadované: %d', $variant->getFullName(), $quantity));
        }
    }

    /**
     * @param RoleMeaning[] $roleMeanings
     */
    public function hasAvailableCapacity(ProductVariant $variant, array $roleMeanings = []): bool
    {
        $available = $this->availableQuantity($variant, $roleMeanings);

        return $available === null || $available > 0;
    }

    /**
     * @param RoleMeaning[] $roleMeanings
     */
    public function isSoldOut(ProductVariant $variant, array $roleMeanings = []): bool
    {
        return ! $this->hasAvailableCapacity($variant, $roleMeanings);
    }

    /**
     * @param RoleMeaning[] $roleMeanings
     */
    public function isLowStock(ProductVariant $variant, int $threshold = 10, array $roleMeanings = []): bool
    {
        $available = $this->availableQuantity($variant, $roleMeanings);

        if ($available === null) {
            return false;
        }

        return $available > 0 && $available <= $threshold;
    }

    /**
     * @return array{
     *     remaining: int|null,
     *     reserved: int,
     *     availableForParticipants: int|null,
     *     unlimited: bool
     * }
     */
    public function getCapacityInfo(ProductVariant $variant): array
    {
        $remaining = $this->remaining($variant);
        $reserved = $variant->getEffectiveReservedForOrganizers() ?? 0;

        return [
            'remaining'                => $remaining,
            'reserved'                 => $reserved,
            'availableForParticipants' => $remaining !== null ? max(0, $remaining - $reserved) : null,
            'unlimited'                => $remaining === null,
        ];
    }

    /**
     * @param RoleMeaning[] $roleMeanings
     */
    private function heldBackFrom(ProductVariant $variant, array $roleMeanings): int
    {
        return RoleMeaning::anyIsOrganizer($roleMeanings)
            ? 0
            : ($variant->getEffectiveReservedForOrganizers() ?? 0);
    }
}
