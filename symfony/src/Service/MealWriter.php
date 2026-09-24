<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\OrderItem;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\ProductStateEnum;
use App\Enum\ProductTagCode;
use App\Repository\OrderItemRepository;
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
        private CapacityManager $capacityManager,
        private PriceIncreaseNotifier $priceIncreaseNotifier,
        private OrderItemRepository $orderItemRepository,
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

        // Fronta oznámení čeká na commit, a ten tu nevyvolá žádný další `postFlush`, který by
        // ji vyprázdnil.
        $this->priceIncreaseNotifier->odesliFrontu();

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
     * Kolik řádků zákazník na každou variantu má — DELETE maže všechny, takže se vrací
     * tolik kusů, kolik jich zmizí.
     *
     * @param int[] $variantIds
     *
     * @return array<int, int> variant_id => počet řádků
     */
    private function pocetKusu(User $customer, int $year, array $variantIds): array
    {
        if ($variantIds === []) {
            return [];
        }

        return array_map('intval', $this->connection->fetchAllKeyValue(
            'SELECT variant_id, COUNT(*) FROM shop_nakupy
             WHERE id_uzivatele = :customer AND rok = :year AND variant_id IN (:variantIds)
             GROUP BY variant_id',
            [
                'customer'   => $customer->getId(),
                'year'       => $year,
                'variantIds' => $variantIds,
            ],
            [
                'variantIds' => ArrayParameterType::INTEGER,
            ],
        ));
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
            $kusu = $this->pocetKusu($customer, $year, array_values($toRemove));

            foreach ($this->orderItemRepository->findBy([
                'customer' => $customer->getId(),
                'year'     => $year,
                'variant'  => array_values($toRemove),
            ]) as $polozka) {
                $this->entityManager->remove($polozka);
            }
            $this->entityManager->flush();

            $this->capacityManager->adjustStock($kusu, +1);
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
        // Additions only: the desk submits the whole selection, so a meal the customer
        // already holds never reaches this. Judging those too would leave a withdrawn meal
        // blocking every later save, with no way to untick it — the cell is locked.
        // An expired `nabizet_do` is deliberately not checked; the desk still sells past it.
        // Archived products never come out of findByTag(), so RETIRED is the whole test.
        $withdrawn = $variant->getProduct();
        if ($withdrawn->getState() === ProductStateEnum::RETIRED) {
            throw new \RuntimeException(sprintf('Jídlo „%s" už není v prodeji.', $withdrawn->getName()));
        }

        $product = $variant->getProduct();
        $discount = $this->discountCalculator->calculateDiscount($product, $customer, $year);
        $order = $this->cartService->getOrCreateCart($customer);

        // `purchase()` čte zásobu z entity kvůli rozhodnutí „neomezeno"; hodnota načtená na
        // začátku requestu už nemusí platit.
        $this->entityManager->refresh($variant);

        try {
            $this->capacityManager->purchase($variant);
        } catch (\RuntimeException) {
            throw new \RuntimeException(sprintf('Jídlo „%s" je bohužel vyprodané.', $product->getName()));
        }

        $item = new OrderItem();
        // Volající může držet odpojenou entitu (admin si mezitím promazal identity map),
        // a `persist()` by ji pak chtěl vložit znovu jako nového uživatele.
        $item->setCustomer($this->entityManager->getReference(User::class, $customer->getId()));
        $item->setProduct($product);
        $item->setVariant($variant);
        $item->setOrder($order);
        $item->setYear($year);
        $item->setPurchasePrice($discount['finalPrice']);
        $item->setDiscountAmount($discount['discountAmount']);
        $item->setDiscountSnapshot($discount['snapshot']);
        $item->snapshotProduct($product, $variant);
        $item->setProductTags($product->getTagNames());

        if ($discount['reason'] !== null) {
            $item->setDiscountReason($discount['reason']);
        }

        $order->addItem($item);
        $this->entityManager->persist($item);
        $this->entityManager->flush();
    }
}
