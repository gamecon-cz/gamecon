<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ProductVariant;
use App\Enum\RoleMeaning;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Remaining stock is capacity minus this year's purchase rows — nothing stores it, so no
 * write can leave it stale. Cancelling a purchase removes its row, which is the whole refund.
 *
 * A variant's capacity is `kusu_vyrobeno` of the catalog row sharing its code (nights and
 * sizes have their own); a variant without such a row takes its product's.
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
                    IF(vlastni_radek.id_predmetu IS NULL, vlastnik.kusu_vyrobeno, vlastni_radek.kusu_vyrobeno) AS kapacita,
                    (
                        SELECT COUNT(*)
                        FROM shop_nakupy
                        WHERE shop_nakupy.rok = :rok
                          AND shop_nakupy.variant_id = product_variant.id
                    ) AS prodano
             FROM product_variant
             INNER JOIN shop_predmety AS vlastnik ON vlastnik.id_predmetu = product_variant.product_id
             LEFT OUTER JOIN shop_predmety AS vlastni_radek ON vlastni_radek.kod_predmetu = product_variant.code
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
     * Locks the variant's capacity row until the surrounding transaction ends, then checks
     * the stock. The sale must be written in that same transaction: a concurrent buyer
     * waits on the lock and then counts the row this one wrote.
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

        $capacityRowId = $this->connection->fetchOne(
            'SELECT IF(vlastni_radek.id_predmetu IS NULL, vlastnik.id_predmetu, vlastni_radek.id_predmetu)
             FROM product_variant
             INNER JOIN shop_predmety AS vlastnik ON vlastnik.id_predmetu = product_variant.product_id
             LEFT OUTER JOIN shop_predmety AS vlastni_radek ON vlastni_radek.kod_predmetu = product_variant.code
             WHERE product_variant.id = :id',
            [
                'id' => $variant->getId(),
            ],
        );
        $capacity = $this->connection->fetchOne(
            'SELECT kusu_vyrobeno FROM shop_predmety WHERE id_predmetu = :id FOR UPDATE',
            [
                'id' => $capacityRowId,
            ],
        );
        if ($capacity === null || $capacity === false) {
            return;
        }

        $sold = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM shop_nakupy WHERE shop_nakupy.rok = :rok AND shop_nakupy.variant_id = :id',
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
