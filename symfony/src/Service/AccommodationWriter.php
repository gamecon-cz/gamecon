<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\OrderItem;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\ProductStateEnum;
use App\Enum\ProductTagCode;
use App\Exception\CapacityExceededException;
use App\Exception\InsufficientPermissionsException;
use App\Exception\InvalidRequestException;
use App\Exception\NoLongerAvailableException;
use App\Exception\UserFacingException;
use App\Repository\OrderItemRepository;
use App\Repository\ProductRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Saves a customer's accommodation as a set: the nights they end up with are exactly the
 * ones passed in, so the write mirrors the grid the read endpoint serves.
 */
class AccommodationWriter
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
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param int[]     $variantIds  nights the customer wants to end up with
     * @param bool|null $mayOverbook null when the caller is not the desk, which alone is offered overbooking
     *
     * @throws UserFacingException when the nights break a rule or a bed is gone
     */
    public function save(
        User $customer,
        array $variantIds,
        int $year,
        bool $maySingleNight,
        ?string $roommate = null,
        ?bool $declined = false,
        bool $sleepingBagsOnly = false,
        ?bool $mayOverbook = null,
        bool $jeOrganizator = false,
    ): int {
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

        $this->capacityManager->beginSaleTransaction();
        try {
            $locked = [
                ...$this->heldNights($customer, $year),
                ...array_keys($variants),
                ...$this->breakfastCanceller->heldBreakfasts($customer, $year),
            ];
            $this->capacityManager->lockInOrder($locked);
            // Počítají se jen datové řádky. Snapshot zrušených snídaní ani log změn osobních
            // údajů se nezapočítává — volající hlásí „změněno N záznamů" a evidence o změně
            // není změna.
            $zmenenychRadku = 0;
            [$kept, $smazano] = $this->removeUnselectedNights($customer, $year, $locked, array_keys($variants));
            $zmenenychRadku += $smazano;
            foreach ($variants as $variantId => $variant) {
                if (! in_array($variantId, $kept, true)) {
                    $zmenenychRadku += $this->addNight($customer, $variant, $year, $mayOverbook, $jeOrganizator);
                }
            }
            $zmenenychRadku += $this->saveAccommodationDetails(
                $customer,
                $year,
                $roommate,
                $declined === null ? null : ($declined && $variants === []),
            );
            $zmenenychRadku += count($this->breakfastCanceller->cancelCovered($customer, $year, $locked));
            $this->connection->commit();
        } catch (\Throwable $error) {
            $this->connection->rollBack();

            throw $error;
        }

        // Fronta oznámení čeká na commit, a ten tu nevyvolá žádný další `postFlush`, který by
        // ji vyprázdnil.
        $this->priceIncreaseNotifier->odesliFrontu();

        $this->entityManager->clear();

        return $zmenenychRadku;
    }

    /**
     * Written to the order, where the answer belongs to its year, and to the account columns
     * as well, because the legacy form still reads those. The second write goes when it does.
     *
     * @param bool|null $declined `null` = volající o téhle volbě nic neví a nechává ji být
     *
     * @return int kolik řádků `uzivatele_hodnoty` se opravdu změnilo — MySQL vrací 0, když
     *             je hodnota stejná, což je přesně „nic se nezměnilo"
     */
    private function saveAccommodationDetails(User $customer, int $year, ?string $roommate, ?bool $declined): int
    {
        $roommate = $roommate === null ? null : (trim($roommate) ?: null);

        $order = $this->cartService->getOrCreateCart($customer);
        $order->setRoommate($roommate);
        if ($declined !== null) {
            $order->setAccommodationDeclined($declined);
        }
        $this->entityManager->flush();

        $puvodni = $this->connection->fetchAssociative(
            'SELECT ubytovan_s, nechce_ubytovani FROM uzivatele_hodnoty WHERE id_uzivatele = :customer',
            [
                'customer' => $customer->getId(),
            ],
        ) ?: [];

        $noveHodnoty = [
            'ubytovan_s' => $roommate ?? '',
        ];
        if ($declined !== null) {
            $noveHodnoty['nechce_ubytovani'] = (int) $declined;
        }

        $nastaveni = implode(', ', array_map(
            static fn (string $sloupec): string => $sloupec . ' = :' . $sloupec,
            array_keys($noveHodnoty),
        ));
        $zmenenychRadku = (int) $this->connection->executeStatement(
            'UPDATE uzivatele_hodnoty SET ' . $nastaveni . ' WHERE id_uzivatele = :customer',
            $noveHodnoty + [
                'customer' => $customer->getId(),
            ],
        );

        // Spolubydlící i „nechci ubytování" jsou osobní údaje, takže jejich změna patří do
        // auditu. Loguje je legacy `Uzivatel`, aby do `uzivatele_hodnoty_log` pořád zapisovalo
        // jediné místo; vlastní tabulka nemá cizí klíč, takže druhé spojení tu nepřekáží.
        \Uzivatel::zalogujZmenuOsobnichUdaju(
            $customer->getId(),
            $noveHodnoty,
            $puvodni,
            null,
            'ubytovani',
        );

        return $zmenenychRadku;
    }

    /**
     * Varianta má přednost před předmětem, stejně jako u merche v `CapacityManager` —
     * import e-shopu zapisuje rezervace právě na varianty.
     *
     * Ne přes `ProductVariant::getEffectiveReservedForOrganizers()`: ten se vrací k rodiči,
     * kterým je u ubytování typ pokoje, ne ta noc.
     */
    private function rezervovanoProOrganizatory(ProductVariant $variant): int
    {
        return (int) ($this->connection->fetchOne(
            'SELECT COALESCE(
                    product_variant.reserved_for_organizers,
                    (SELECT reserved_for_organizers FROM shop_predmety
                     WHERE shop_predmety.kod_predmetu = product_variant.code),
                    0
                )
             FROM product_variant WHERE product_variant.id = :varianta',
            [
                'varianta' => $variant->getId(),
            ],
        ) ?: 0);
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
                throw new InvalidRequestException($this->translator->trans('accommodation.night_not_offered', [
                    '%id%' => $variantId,
                ], 'errors'));
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
            throw new InvalidRequestException($this->translator->trans('accommodation.at_least_two_nights', [], 'errors'));
        }

        for ($i = 1, $count = count($days); $i < $count; ++$i) {
            if ($days[$i] !== $days[$i - 1] + 1) {
                throw new InvalidRequestException($this->translator->trans('accommodation.consecutive_nights', [], 'errors'));
            }
        }
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
                'variantIds' => \Doctrine\DBAL\ArrayParameterType::INTEGER,
            ],
        ));
    }

    /**
     * Held is read again after the locks, as in MealWriter::removeUnselected().
     *
     * @param int[] $locked
     * @param int[] $keepVariantIds
     *
     * @return array{0: int[], 1: int} ponechané varianty a počet smazaných řádků
     */
    private function removeUnselectedNights(User $customer, int $year, array $locked, array $keepVariantIds): array
    {
        $held = $this->heldNights($customer, $year);

        $smazano = 0;
        $toRemove = array_intersect(array_diff($held, $keepVariantIds), $locked);
        if ($toRemove !== []) {
            $kusu = $this->pocetKusu($customer, $year, array_values($toRemove));
            $keSmazani = $this->orderItemRepository->findBy([
                'customer' => $customer->getId(),
                'year'     => $year,
                'variant'  => array_values($toRemove),
            ]);
            foreach ($keSmazani as $polozka) {
                $this->entityManager->remove($polozka);
            }
            $this->entityManager->flush();
            // Počet řádků, ne entit: zákazník může mít na jednu variantu víc nákupů.
            $smazano = array_sum($kusu);
        }

        return [array_values(array_intersect($held, $keepVariantIds)), $smazano];
    }

    private function addNight(
        User $customer,
        ProductVariant $variant,
        int $year,
        ?bool $mayOverbook,
        bool $jeOrganizator,
    ): int {
        $product = $variant->getProduct();
        // Additions only, as in MealWriter::addMeal(): a held night withdrawn later must not block
        // the next save. The night's own state, as the grid judges it (ProductRepository::
        // capacityByVariantCode()); archived room types never come out of findByTag().
        if ($variant->getState() === ProductStateEnum::RETIRED) {
            throw new NoLongerAvailableException($this->translator->trans('accommodation.withdrawn', [
                '%product%' => $product->getName(), '%night%' => $variant->getNightName(),
            ], 'errors'));
        }
        // Den nese varianta: typ pokoje žádný nemá, takže bez něj by nárok na konkrétní noc
        // zdarma nikdy nesedl.
        $discount = $this->discountCalculator->calculateDiscount(
            $product,
            $customer,
            $year,
            $variant->getAccommodationDay(),
        );
        $order = $this->cartService->getOrCreateCart($customer);

        // Already locked by save(); read under the lock so a bed taken meanwhile is counted.
        $capacity = $this->connection->fetchOne(
            'SELECT capacity FROM product_variant WHERE id = :variant FOR UPDATE',
            [
                'variant' => $variant->getId(),
            ],
        );
        if ($mayOverbook !== true && $capacity !== null) {
            // A locking read, as in CapacityManager::lockForSale(): a plain one relies on the
            // caller's READ COMMITTED to see purchases committed while waiting for the lock.
            $sold = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM shop_nakupy WHERE variant_id = :variant AND rok = :year LOCK IN SHARE MODE',
                [
                    'variant' => $variant->getId(),
                    'year'    => $year,
                ],
            );
            // Beds held back for organizers are off limits to participants only.
            $heldBack = $jeOrganizator ? 0 : $this->rezervovanoProOrganizatory($variant);
            if ((int) $capacity - $heldBack <= $sold) {
                $parameters = [
                    '%product%' => $product->getName(),
                    '%night%'   => $variant->getNightName(),
                ];
                // Telling the desk the night is "obsazené" when the real answer is "you may not
                // overbook" sends them hunting for a bed that exists.
                if ($mayOverbook === false) {
                    throw new InsufficientPermissionsException($this->translator->trans('accommodation.overbooking_not_permitted', $parameters, 'errors'));
                }
                throw new CapacityExceededException($this->translator->trans('accommodation.night_full', $parameters, 'errors'));
            }
        }

        $item = new OrderItem();
        // The caller may hold a detached user, which persist() would try to insert again.
        $item->setCustomer($this->entityManager->getReference(User::class, $customer->getId()));
        $item->setVariant($variant);
        $item->setOrder($order);
        $item->setYear($year);
        $item->setPurchasePrice($discount['finalPrice']);
        $item->setDiscountAmount($discount['discountAmount']);
        $item->setDiscountSnapshot($discount['snapshot']);
        if ($discount['reason'] !== null) {
            $item->setDiscountReason($discount['reason']);
        }
        // Named after the room type, as the night's variant is only a day of it.
        $item->snapshotProduct($product, $variant);
        $item->setProductTags($product->getTagNames());

        $order->addItem($item);
        $this->entityManager->persist($item);
        $this->entityManager->flush();

        return 1;
    }
}
