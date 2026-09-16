<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\ProductTagCode;
use App\Repository\ProductRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Saves a customer's accommodation as a set: the nights they end up with are exactly the
 * ones passed in, so the write mirrors the grid the read endpoint serves.
 */
class AccommodationWriter
{
    public const ERROR_AT_LEAST_TWO_NIGHTS = 'Ubytování je možné objednat nejméně na dvě noci.';

    public const ERROR_CONSECUTIVE_NIGHTS = 'Objednané noci musí na sebe navazovat.';

    public const ERROR_OVERBOOKING_NOT_PERMITTED = 'Ubytování „%s" na %s je plné; přeplnit ho smí jen šéf infopultu.';

    public function __construct(
        private Connection $connection,
        private EntityManagerInterface $entityManager,
        private ProductRepository $productRepository,
        private CartService $cartService,
        private DiscountCalculator $discountCalculator,
        private BreakfastCanceller $breakfastCanceller,
    ) {
    }

    /**
     * @param int[] $variantIds nights the customer wants to end up with
     *
     * @throws \RuntimeException when the nights break a rule or a bed is gone
     */
    public function save(
        User $customer,
        array $variantIds,
        int $year,
        bool $maySingleNight,
        ?string $roommate = null,
        bool $declined = false,
        bool $sleepingBagsOnly = false,
        bool $mayOverbook = false,
    ): void {
        $variants = $this->loadVariants($variantIds, $sleepingBagsOnly);

        // Only judge the nights when they actually change. The legacy admin screens can book a
        // set these rules would reject, and re-validating an untouched booking would leave
        // such a customer unable to save even their roommate.
        if ($this->nightsChanged($customer, $year, array_keys($variants))) {
            $this->validateNights(array_map(
                static fn (ProductVariant $variant): int => (int) $variant->getAccommodationDay(),
                $variants,
            ), $maySingleNight);
        }

        $this->connection->beginTransaction();
        try {
            $kept = $this->removeUnselectedNights($customer, $year, array_keys($variants));
            foreach ($variants as $variantId => $variant) {
                if (! in_array($variantId, $kept, true)) {
                    $this->addNight($customer, $variant, $year, $mayOverbook);
                }
            }
            $this->saveAccommodationDetails($customer, $year, $roommate, $declined && $variants === []);
            $this->breakfastCanceller->cancelCovered($customer, $year);
            $this->connection->commit();
        } catch (\Throwable $error) {
            $this->connection->rollBack();

            throw $error;
        }

        $this->entityManager->clear();
    }

    /**
     * Written to the order, where the answer belongs to its year, and to the account columns
     * as well, because the legacy form still reads those. The second write goes when it does.
     */
    private function saveAccommodationDetails(User $customer, int $year, ?string $roommate, bool $declined): void
    {
        $roommate = $roommate === null ? null : (trim($roommate) ?: null);

        $order = $this->cartService->getOrCreateCart($customer);
        $order->setRoommate($roommate);
        $order->setAccommodationDeclined($declined);
        $this->entityManager->flush();

        $this->connection->executeStatement(
            'UPDATE uzivatele_hodnoty SET ubytovan_s = :spolubydlici, nechce_ubytovani = :nechce
             WHERE id_uzivatele = :customer',
            [
                'spolubydlici' => $roommate ?? '',
                'nechce'       => (int) $declined,
                'customer'     => $customer->getId(),
            ],
        );
    }

    /**
     * @return int[] accommodation variant ids this customer holds for the year
     */
    private function heldNights(User $customer, int $year): array
    {
        $held = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT shop_nakupy.variant_id
             FROM shop_nakupy
             JOIN product_variant ON product_variant.id = shop_nakupy.variant_id
             WHERE shop_nakupy.id_uzivatele = :customer
               AND shop_nakupy.rok = :year
               AND product_variant.accommodation_day IS NOT NULL
               AND EXISTS (
                   SELECT 1 FROM product_product_tag
                   JOIN product_tag ON product_tag.id = product_product_tag.tag_id
                   WHERE product_product_tag.product_id = product_variant.product_id
                     AND product_tag.code = :tag
               )',
            [
                'customer' => $customer->getId(),
                'year'     => $year,
                'tag'      => ProductTagCode::UBYTOVANI->value,
            ],
        );

        return array_map('intval', $held);
    }

    /**
     * @param int[] $variantIds
     */
    private function nightsChanged(User $customer, int $year, array $variantIds): bool
    {
        $held = $this->heldNights($customer, $year);
        sort($held);
        sort($variantIds);

        return $held !== $variantIds;
    }

    /**
     * @param int[] $variantIds
     *
     * @return array<int, ProductVariant> keyed by variant id
     */
    private function loadVariants(array $variantIds, bool $sleepingBagsOnly): array
    {
        if ($variantIds === []) {
            return [];
        }

        $variants = [];
        foreach ($this->productRepository->findByTag(ProductTagCode::UBYTOVANI) as $product) {
            // The grid hides room types under this restriction, so accepting one here would
            // let a hand-made request book what the customer cannot see.
            if ($sleepingBagsOnly && ! $product->hasTag(ProductTagCode::SPACAK->value)) {
                continue;
            }
            foreach ($product->getVariants() as $variant) {
                $id = $variant->getId();
                if ($id !== null && in_array($id, $variantIds, true) && $variant->getAccommodationDay() !== null) {
                    $variants[$id] = $variant;
                }
            }
        }

        foreach ($variantIds as $variantId) {
            if (! isset($variants[$variantId])) {
                throw new \RuntimeException(sprintf('Noc %d není nabízeným ubytováním.', $variantId));
            }
        }

        return $variants;
    }

    /**
     * @param int[] $days
     */
    private function validateNights(array $days, bool $maySingleNight): void
    {
        $days = array_values(array_unique($days));
        sort($days, SORT_NUMERIC);

        if ($days === []) {
            return;
        }

        if (! $maySingleNight && count($days) < 2) {
            throw new \RuntimeException(self::ERROR_AT_LEAST_TWO_NIGHTS);
        }

        for ($i = 1, $count = count($days); $i < $count; ++$i) {
            if ($days[$i] !== $days[$i - 1] + 1) {
                throw new \RuntimeException(self::ERROR_CONSECUTIVE_NIGHTS);
            }
        }
    }

    /**
     * @param int[] $keepVariantIds
     *
     * @return int[] variant ids the customer already had and keeps
     */
    private function removeUnselectedNights(User $customer, int $year, array $keepVariantIds): array
    {
        $held = $this->heldNights($customer, $year);

        $toRemove = array_diff($held, $keepVariantIds);
        if ($toRemove !== []) {
            $this->connection->executeStatement(
                'DELETE FROM shop_nakupy
                 WHERE id_uzivatele = :customer AND rok = :year AND variant_id IN (:variantIds)',
                [
                    'customer'   => $customer->getId(),
                    'year'       => $year,
                    'variantIds' => array_values($toRemove),
                ],
                [
                    'variantIds' => \Doctrine\DBAL\ArrayParameterType::INTEGER,
                ],
            );
        }

        return array_values(array_intersect($held, $keepVariantIds));
    }

    /**
     * Capacity is counted from shop_nakupy because remaining_quantity is stale while the admin
     * and infopult screens still sell without touching it. id_predmetu is the night's own
     * legacy row, not the variant's parent — the day-variant migration reparented variants
     * onto one owner, and every legacy consumer reads ubytovani_den off id_predmetu.
     */
    private function addNight(
        User $customer,
        ProductVariant $variant,
        int $year,
        bool $mayOverbook,
    ): void {
        $product = $variant->getProduct();
        $discount = $this->discountCalculator->calculateDiscount($product, $customer, $year);
        $order = $this->cartService->getOrCreateCart($customer);

        // The count below reads a snapshot, so two writers would both see the last bed free.
        // Locking the night's capacity row first makes them queue instead.
        $this->connection->executeQuery(
            'SELECT kusu_vyrobeno FROM shop_predmety WHERE kod_predmetu = :variantCode FOR UPDATE',
            [
                'variantCode' => $variant->getCode(),
            ],
        );

        $vlozeno = $this->connection->executeStatement(
            'INSERT INTO shop_nakupy
                (id_uzivatele, id_predmetu, variant_id, order_id, rok, cena_nakupni, datum,
                 product_name, product_code, product_tags, variant_name, variant_code)
             SELECT :customer, noc.id_predmetu, :variant, :order, :year, :price, NOW(),
                    :productName, :productCode, :productTags, :variantName, :variantCode
             FROM shop_predmety AS noc
             WHERE noc.kod_predmetu = :variantCode
               AND (
                   :presKapacitu = 1
                   OR noc.kusu_vyrobeno IS NULL
                   OR noc.kusu_vyrobeno > (
                       SELECT COUNT(*) FROM shop_nakupy AS prodane
                       WHERE prodane.variant_id = :variant AND prodane.rok = :year
                   )
               )',
            [
                'customer'     => $customer->getId(),
                'variant'      => $variant->getId(),
                'order'        => $order->getId(),
                'year'         => $year,
                'price'        => $discount['finalPrice'],
                'productName'  => $product->getName(),
                'productCode'  => $product->getCode(),
                'productTags'  => json_encode($product->getTagNames(), JSON_THROW_ON_ERROR),
                'variantName'  => $variant->getName(),
                'variantCode'  => $variant->getCode(),
                'presKapacitu' => (int) $mayOverbook,
            ],
        );

        if ($vlozeno === 0) {
            // The override makes the capacity test always pass, so getting here at all means
            // the caller did not have it. Telling the desk the night is "obsazené" when the
            // real answer is "you may not overbook" sends them hunting for a bed that exists.
            throw new \RuntimeException(sprintf(self::ERROR_OVERBOOKING_NOT_PERMITTED, $product->getName(), $variant->getName()));
        }
    }
}
