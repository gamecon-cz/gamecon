<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * A night whose price already includes breakfast makes a separately bought breakfast for the
 * next morning redundant, so booking one cancels it. What was cancelled is recorded, so the
 * customer can be offered it back if they drop the night again.
 */
class BreakfastCanceller
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * Cancels the breakfasts the customer's booked nights already cover, and remembers the
     * selection they had before it happened.
     *
     * @return int[] variant ids that were cancelled
     */
    public function cancelCovered(User $customer, int $year): array
    {
        $drzene = $this->drzeneSnidane($customer, $year);
        if ($drzene === []) {
            return [];
        }

        $kryte = array_values(array_intersect_key($drzene, array_flip($this->kryteRana($customer, $year))));
        if ($kryte === []) {
            return [];
        }

        // Snapshot the whole selection, not just what is being cancelled: restoring means
        // putting back the breakfasts they had, and the untouched ones are still theirs.
        $this->ulozSnapshot($customer, $year, array_values($drzene));

        $this->connection->executeStatement(
            'DELETE FROM shop_nakupy
             WHERE id_uzivatele = :customer AND rok = :year AND variant_id IN (:variantIds)',
            [
                'customer'   => $customer->getId(),
                'year'       => $year,
                'variantIds' => $kryte,
            ],
            [
                'variantIds' => ArrayParameterType::INTEGER,
            ],
        );

        return $kryte;
    }

    /**
     * Breakfasts the customer could put back: the last selection, minus whatever they hold
     * now and minus anything a still-booked night would only cancel again.
     *
     * @return int[] variant ids
     */
    public function restorable(User $customer, int $year): array
    {
        $snapshot = $this->snapshot($customer, $year);
        if ($snapshot === []) {
            return [];
        }

        $drzene = $this->drzeneSnidane($customer, $year);
        $kryteRana = $this->kryteRana($customer, $year);

        $nabidnout = [];
        foreach ($this->snidaneVarianty() as $den => $variantId) {
            if (in_array($variantId, $snapshot, true)
                && ! isset($drzene[$den])
                && ! in_array($den, $kryteRana, true)
            ) {
                $nabidnout[] = $variantId;
            }
        }

        return $nabidnout;
    }

    /**
     * @param int[] $variantIds
     */
    public function ulozSnapshot(User $customer, int $year, array $variantIds): void
    {
        $this->connection->executeStatement(
            'INSERT INTO shop_snidane_snapshot (id_uzivatele, rok, variant_ids, ulozeno)
             VALUES (:customer, :year, :variantIds, NOW())
             ON DUPLICATE KEY UPDATE variant_ids = VALUES(variant_ids), ulozeno = VALUES(ulozeno)',
            [
                'customer'   => $customer->getId(),
                'year'       => $year,
                'variantIds' => json_encode(array_values($variantIds), JSON_THROW_ON_ERROR),
            ],
        );
    }

    /**
     * @return int[] variant ids
     */
    private function snapshot(User $customer, int $year): array
    {
        $ulozene = $this->connection->fetchOne(
            'SELECT variant_ids FROM shop_snidane_snapshot WHERE id_uzivatele = :customer AND rok = :year',
            [
                'customer' => $customer->getId(),
                'year'     => $year,
            ],
        );

        if (! is_string($ulozene)) {
            return [];
        }

        $dekodovane = json_decode($ulozene, true, 512, JSON_THROW_ON_ERROR);

        return is_array($dekodovane) ? array_map('intval', $dekodovane) : [];
    }

    /**
     * @return array<int, int> breakfast variant id per morning the customer holds
     */
    private function drzeneSnidane(User $customer, int $year): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT product_variant.accommodation_day AS den, product_variant.id
             FROM shop_nakupy
             JOIN product_variant ON product_variant.id = shop_nakupy.variant_id
             JOIN shop_predmety ON shop_predmety.id_predmetu = product_variant.product_id
             WHERE shop_nakupy.id_uzivatele = :customer
               AND shop_nakupy.rok = :year
               AND TRIM(shop_predmety.nazev) LIKE :snidane',
            [
                'customer' => $customer->getId(),
                'year'     => $year,
                'snidane'  => 'Snídaně%',
            ],
        );

        $drzene = [];
        foreach ($rows as $row) {
            $drzene[(int) $row['den']] = (int) $row['id'];
        }

        return $drzene;
    }

    /**
     * Mornings after a booked night whose price already includes breakfast — night N covers
     * the morning of day N+1.
     *
     * @return int[] day indexes
     */
    private function kryteRana(User $customer, int $year): array
    {
        $dny = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT product_variant.accommodation_day + 1
             FROM shop_nakupy
             JOIN product_variant ON product_variant.id = shop_nakupy.variant_id
             JOIN shop_predmety ON shop_predmety.id_predmetu = product_variant.product_id
             WHERE shop_nakupy.id_uzivatele = :customer
               AND shop_nakupy.rok = :year
               AND shop_predmety.breakfast_included = 1
               AND product_variant.accommodation_day IS NOT NULL',
            [
                'customer' => $customer->getId(),
                'year'     => $year,
            ],
        );

        return array_map('intval', $dny);
    }

    /**
     * @return array<int, int> breakfast variant id per morning
     */
    private function snidaneVarianty(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT product_variant.accommodation_day AS den, product_variant.id
             FROM product_variant
             JOIN shop_predmety ON shop_predmety.id_predmetu = product_variant.product_id
             WHERE TRIM(shop_predmety.nazev) LIKE :snidane
               AND shop_predmety.archived_at IS NULL
               AND product_variant.accommodation_day IS NOT NULL',
            [
                'snidane' => 'Snídaně%',
            ],
        );

        $varianty = [];
        foreach ($rows as $row) {
            $varianty[(int) $row['den']] = (int) $row['id'];
        }

        return $varianty;
    }
}
