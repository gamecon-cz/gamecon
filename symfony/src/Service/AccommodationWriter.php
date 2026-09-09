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
        $this->overNoci(array_map(
            static fn (ProductVariant $variant): int => (int) $variant->getAccommodationDay(),
            $varianty,
        ), $muzeJednuNoc);

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
        $drzene = array_map('intval', $drzene);

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
     * The guard sits in the INSERT's WHERE, so two people racing for the last bed cannot both
     * win. It counts shop_nakupy instead of decrementing remaining_quantity because the admin
     * and infopult screens still sell without touching that column; this becomes
     * CapacityManager::purchase() once they are ported.
     */
    private function pridejNoc(User $customer, ProductVariant $variant, int $year): void
    {
        $product = $variant->getProduct();
        $sleva = $this->discountCalculator->calculateDiscount($product, $customer, $year);
        $order = $this->cartService->getOrCreateCart($customer);

        $vlozeno = $this->connection->executeStatement(
            'INSERT INTO shop_nakupy
                (id_uzivatele, id_predmetu, variant_id, order_id, rok, cena_nakupni, datum,
                 product_name, product_code, product_tags, variant_name, variant_code)
             SELECT :customer, :product, :variant, :order, :year, :price, NOW(),
                    :productName, :productCode, :productTags, :variantName, :variantCode
             FROM DUAL
             WHERE (
                 SELECT kusu_vyrobeno FROM shop_predmety WHERE kod_predmetu = :variantCode
             ) IS NULL
             OR (
                 SELECT kusu_vyrobeno FROM shop_predmety WHERE kod_predmetu = :variantCode
             ) > (
                 SELECT COUNT(*) FROM shop_nakupy AS prodane
                 WHERE prodane.variant_id = :variant AND prodane.rok = :year
             )',
            [
                'customer'    => $customer->getId(),
                'product'     => $product->getId(),
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
