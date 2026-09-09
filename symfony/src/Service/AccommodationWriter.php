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
readonly class AccommodationWriter
{
    public const CHYBA_MINIMALNE_DVE_NOCI = 'Ubytování je možné objednat nejméně na dvě noci.';

    public const CHYBA_NAVAZUJICI_NOCI = 'Objednané noci musí na sebe navazovat.';

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
        bool $muzeJednuNoc,
        ?string $spolubydlici = null,
        bool $nechceUbytovani = false,
    ): void {
        $varianty = $this->nactiVarianty($variantIds);

        // Only judge the nights when they actually change. The legacy admin screens can book a
        // set these rules would reject, and re-validating an untouched booking would leave
        // such a customer unable to save even their roommate.
        if ($this->zmenaNoci($customer, $year, array_keys($varianty))) {
            $this->overNoci(array_map(
                static fn (ProductVariant $variant): int => (int) $variant->getAccommodationDay(),
                $varianty,
            ), $muzeJednuNoc);
        }

        $this->connection->beginTransaction();
        try {
            $ponechane = $this->smazNevybraneNoci($customer, $year, array_keys($varianty));
            foreach ($varianty as $variantId => $variant) {
                if (! in_array($variantId, $ponechane, true)) {
                    $this->pridejNoc($customer, $variant, $year);
                }
            }
            $this->ulozUdajeOUbytovani($customer, $year, $spolubydlici, $nechceUbytovani && $varianty === []);
            $this->breakfastCanceller->cancelCovered($customer, $year);
            $this->connection->commit();
        } catch (\Throwable $chyba) {
            $this->connection->rollBack();

            throw $chyba;
        }

        $this->entityManager->clear();
    }

    /**
     * Written to the order, where the answer belongs to its year, and to the account columns
     * as well, because the legacy form still reads those. The second write goes when it does.
     */
    private function ulozUdajeOUbytovani(User $customer, int $year, ?string $spolubydlici, bool $nechce): void
    {
        $spolubydlici = $spolubydlici === null ? null : (trim($spolubydlici) ?: null);

        $order = $this->cartService->getOrCreateCart($customer);
        $order->setRoommate($spolubydlici);
        $order->setAccommodationDeclined($nechce);
        $this->entityManager->flush();

        $this->connection->executeStatement(
            'UPDATE uzivatele_hodnoty SET ubytovan_s = :spolubydlici, nechce_ubytovani = :nechce
             WHERE id_uzivatele = :customer',
            [
                'spolubydlici' => $spolubydlici ?? '',
                'nechce'       => (int) $nechce,
                'customer'     => $customer->getId(),
            ],
        );
    }

    /**
     * @return int[] accommodation variant ids this customer holds for the year
     */
    private function drzeneNoci(User $customer, int $year): array
    {
        $drzene = $this->connection->fetchFirstColumn(
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

        return array_map('intval', $drzene);
    }

    /**
     * @param int[] $variantIds
     */
    private function zmenaNoci(User $customer, int $year, array $variantIds): bool
    {
        $drzene = $this->drzeneNoci($customer, $year);
        sort($drzene);
        sort($variantIds);

        return $drzene !== $variantIds;
    }

    /**
     * @param int[] $variantIds
     *
     * @return array<int, ProductVariant> keyed by variant id
     */
    private function nactiVarianty(array $variantIds): array
    {
        if ($variantIds === []) {
            return [];
        }

        $varianty = [];
        foreach ($this->productRepository->findByTag(ProductTagCode::UBYTOVANI) as $product) {
            foreach ($product->getVariants() as $variant) {
                $id = $variant->getId();
                if ($id !== null && in_array($id, $variantIds, true) && $variant->getAccommodationDay() !== null) {
                    $varianty[$id] = $variant;
                }
            }
        }

        foreach ($variantIds as $variantId) {
            if (! isset($varianty[$variantId])) {
                throw new \RuntimeException(sprintf('Noc %d není nabízeným ubytováním.', $variantId));
            }
        }

        return $varianty;
    }

    /**
     * @param int[] $dny
     */
    private function overNoci(array $dny, bool $muzeJednuNoc): void
    {
        $dny = array_values(array_unique($dny));
        sort($dny, SORT_NUMERIC);

        if ($dny === []) {
            return;
        }

        if (! $muzeJednuNoc && count($dny) < 2) {
            throw new \RuntimeException(self::CHYBA_MINIMALNE_DVE_NOCI);
        }

        for ($i = 1, $pocet = count($dny); $i < $pocet; ++$i) {
            if ($dny[$i] !== $dny[$i - 1] + 1) {
                throw new \RuntimeException(self::CHYBA_NAVAZUJICI_NOCI);
            }
        }
    }

    /**
     * @param int[] $ponechatVariantIds
     *
     * @return int[] variant ids the customer already had and keeps
     */
    private function smazNevybraneNoci(User $customer, int $year, array $ponechatVariantIds): array
    {
        $drzene = $this->drzeneNoci($customer, $year);

        $kSmazani = array_diff($drzene, $ponechatVariantIds);
        if ($kSmazani !== []) {
            $this->connection->executeStatement(
                'DELETE FROM shop_nakupy
                 WHERE id_uzivatele = :customer AND rok = :year AND variant_id IN (:variantIds)',
                [
                    'customer'   => $customer->getId(),
                    'year'       => $year,
                    'variantIds' => array_values($kSmazani),
                ],
                [
                    'variantIds' => \Doctrine\DBAL\ArrayParameterType::INTEGER,
                ],
            );
        }

        return array_values(array_intersect($drzene, $ponechatVariantIds));
    }

    /**
     * Capacity is counted from shop_nakupy because remaining_quantity is stale while the admin
     * and infopult screens still sell without touching it. id_predmetu is the night's own
     * legacy row, not the variant's parent — the day-variant migration reparented variants
     * onto one owner, and every legacy consumer reads ubytovani_den off id_predmetu.
     */
    private function pridejNoc(User $customer, ProductVariant $variant, int $year): void
    {
        $product = $variant->getProduct();
        $sleva = $this->discountCalculator->calculateDiscount($product, $customer, $year);
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
                   noc.kusu_vyrobeno IS NULL
                   OR noc.kusu_vyrobeno > (
                       SELECT COUNT(*) FROM shop_nakupy AS prodane
                       WHERE prodane.variant_id = :variant AND prodane.rok = :year
                   )
               )',
            [
                'customer'    => $customer->getId(),
                'variant'     => $variant->getId(),
                'order'       => $order->getId(),
                'year'        => $year,
                'price'       => $sleva['finalPrice'],
                'productName' => $product->getName(),
                'productCode' => $product->getCode(),
                'productTags' => json_encode($product->getTagNames(), JSON_THROW_ON_ERROR),
                'variantName' => $variant->getName(),
                'variantCode' => $variant->getCode(),
            ],
        );

        if ($vlozeno === 0) {
            throw new \RuntimeException(sprintf('Ubytování „%s" na %s je bohužel obsazené.', $product->getName(), $variant->getName()));
        }
    }
}
