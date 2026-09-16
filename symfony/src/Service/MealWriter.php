<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\ProductTagCode;
use App\Repository\ProductRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Saving a whole meal selection at once, the way the admin form does it.
 *
 * The cart buys meals one at a time, which suits a participant clicking through a matrix but
 * not a desk that submits the finished grid: it would take a request per meal and leave the
 * customer half-changed if one failed.
 */
class MealWriter
{
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
     * @param int[] $variantIds meals the customer should end up with
     *
     * @throws \RuntimeException when a variant is not an offered meal
     */
    public function save(User $customer, array $variantIds, int $year): void
    {
        $variants = $this->loadVariants($variantIds);

        $this->connection->beginTransaction();
        try {
            $kept = $this->removeUnselected($customer, $year, array_keys($variants));
            foreach ($variants as $variantId => $variant) {
                if (! in_array($variantId, $kept, true)) {
                    $this->addMeal($customer, $variant, $year);
                }
            }

            // After the additions, so a breakfast the hotel covers is dropped even when this
            // very request ordered it. Legacy filtered such breakfasts out instead, so it also
            // never remembered them; here the canceller snapshots one, and the participant is
            // offered it back if the covering night later goes away. That is deliberate.
            $this->breakfastCanceller->cancelCovered($customer, $year);
            $this->connection->commit();
        } catch (\Throwable $error) {
            $this->connection->rollBack();

            throw $error;
        }

        $this->entityManager->clear();
    }

    /**
     * @param int[] $variantIds
     *
     * @return array<int, ProductVariant> keyed by variant id
     */
    private function loadVariants(array $variantIds): array
    {
        $variants = [];
        foreach ($this->productRepository->findByTag(ProductTagCode::JIDLO) as $product) {
            foreach ($product->getVariants() as $variant) {
                $id = $variant->getId();
                if ($id !== null && in_array($id, $variantIds, true)) {
                    $variants[$id] = $variant;
                }
            }
        }

        foreach ($variantIds as $variantId) {
            if (! isset($variants[$variantId])) {
                throw new \RuntimeException(sprintf('Jídlo %d není v nabídce.', $variantId));
            }
        }

        return $variants;
    }

    /**
     * @param int[] $keepVariantIds
     *
     * @return int[] variant ids the customer already had and keeps
     */
    private function removeUnselected(User $customer, int $year, array $keepVariantIds): array
    {
        $held = $this->heldMeals($customer, $year);

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
                    'variantIds' => ArrayParameterType::INTEGER,
                ],
            );
        }

        return array_values(array_intersect($held, $keepVariantIds));
    }

    /**
     * Public because the desk reads the selection separately from writing it: the catalogue
     * endpoint is customer-agnostic, and the participant's own matrix reads this off their cart.
     *
     * @return int[] variant ids of meals the customer holds this year
     */
    public function heldMeals(User $customer, int $year): array
    {
        return array_map('intval', $this->connection->fetchFirstColumn(
            "SELECT DISTINCT nakupy.variant_id
             FROM shop_nakupy AS nakupy
             JOIN product_variant AS varianty ON varianty.id = nakupy.variant_id
             JOIN product_product_tag AS vazba ON vazba.product_id = varianty.product_id
             JOIN product_tag AS tag ON tag.id = vazba.tag_id
             WHERE nakupy.id_uzivatele = :customer AND nakupy.rok = :year AND tag.code = 'jidlo'
               AND nakupy.variant_id IS NOT NULL",
            [
                'customer' => $customer->getId(),
                'year'     => $year,
            ],
        ));
    }

    /**
     * id_predmetu is the parent product's, which is right only because a meal is one variant of
     * one product. Accommodation had to read it off the night's own row, since its variants were
     * reparented onto shared owner products — split meals the same way and this becomes wrong.
     */
    private function addMeal(User $customer, ProductVariant $variant, int $year): void
    {
        $product = $variant->getProduct();
        $discount = $this->discountCalculator->calculateDiscount($product, $customer, $year);
        $order = $this->cartService->getOrCreateCart($customer);

        // The count below reads a snapshot, so two writers would both see the last portion
        // free. Locking the meal's stock row first makes them queue instead.
        $this->connection->executeQuery(
            'SELECT kusu_vyrobeno FROM shop_predmety WHERE kod_predmetu = :variantCode FOR UPDATE',
            [
                'variantCode' => $variant->getCode(),
            ],
        );

        $inserted = $this->connection->executeStatement(
            'INSERT INTO shop_nakupy
                (id_uzivatele, id_predmetu, variant_id, order_id, rok, cena_nakupni, datum,
                 product_name, product_code, product_tags, variant_name, variant_code)
             SELECT :customer, :product, :variant, :order, :year, :price, NOW(),
                    :productName, :productCode, :productTags, :variantName, :variantCode
             FROM shop_predmety AS jidlo
             WHERE jidlo.kod_predmetu = :variantCode
               AND (
                   jidlo.kusu_vyrobeno IS NULL
                   OR jidlo.kusu_vyrobeno > (
                       SELECT COUNT(*) FROM shop_nakupy AS prodane
                       WHERE prodane.variant_id = :variant AND prodane.rok = :year
                   )
               )',
            [
                'customer'    => $customer->getId(),
                'product'     => $product->getId(),
                'variant'     => $variant->getId(),
                'order'       => $order->getId(),
                'year'        => $year,
                'price'       => $discount['finalPrice'],
                'productName' => $product->getName(),
                'productCode' => $product->getCode(),
                'productTags' => json_encode($product->getTagNames(), JSON_THROW_ON_ERROR),
                'variantName' => $variant->getName(),
                'variantCode' => $variant->getCode(),
            ],
        );

        if ($inserted === 0) {
            throw new \RuntimeException(sprintf('Jídlo „%s" je bohužel vyprodané.', $product->getName()));
        }
    }
}
