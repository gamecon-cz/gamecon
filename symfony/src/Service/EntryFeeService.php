<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\OrderItemRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * The voluntary entry fee ("dobrovolné vstupné") is a donation, not a normal purchase: the
 * customer names the price, there is no capacity, and paying twice means changing the single
 * amount rather than owning two of something. It therefore does not go through the cart, whose
 * prices come from the product.
 *
 * A second product for payments made late ("pozdě" in the name) is no longer offered — it sits at
 * StavPredmetu::MIMO and was last charged in 2022 — so nothing here writes to it, the same as for
 * any other discontinued product. Purchases people already made keep their values and keep counting
 * towards their total; only this year's product is ever written.
 */
readonly class EntryFeeService
{
    /**
     * Tells the no-longer-offered late-payment product apart from the ordinary one; both are
     * tagged 'vstupne' and only the name distinguishes them.
     */
    private const LATE_NAME_MARKER = 'pozdě';

    private const LAST_YEAR_AVERAGE_SETTING = 'PRUMERNE_LONSKE_VSTUPNE';

    /**
     * The slider maps an amount onto its width as (amount / SLIDER_MAXIMUM) ** GAMMA_CORRECTION,
     * so the small amounts most people choose get a usable share of the track. The frontend needs
     * the same exponent to place the handle, so it is exposed rather than duplicated there.
     *
     * @see https://cs.wikipedia.org/wiki/Gama_korekce
     */
    public const GAMMA_CORRECTION = 0.5;

    public const SLIDER_MAXIMUM = 1000;

    /**
     * cena_nakupni is NUMERIC(6,2), so anything above this cannot be stored: strict mode rejects
     * it and a permissive one silently truncates to 9999.99.
     */
    public const MAXIMUM_AMOUNT = 9999;

    /**
     * Last year's average has not been computed, so there is nowhere to put the slider marker.
     * Distinct from 0, which would legitimately draw it at the far left.
     */
    public const AVERAGE_UNKNOWN = -1;

    public function __construct(
        private ProductRepository $productRepository,
        private OrderItemRepository $orderItemRepository,
        private EntityManagerInterface $entityManager,
        private CurrentYearProviderInterface $currentYearProvider,
    ) {
    }

    public function getAmount(User $user): string
    {
        return $this->paidForProduct($user, $this->entryFeeProduct());
    }

    public function setAmount(User $user, int $amount): string
    {
        $product = $this->entryFeeProduct();
        $year = $this->currentYearProvider->getCurrentYear();

        $this->entityManager->wrapInTransaction(function () use ($user, $amount, $product, $year): void {
            // Locking the row up front keeps two concurrent saves — the slider debounce makes those
            // routine — from each deciding to insert their own, which would double the donation.
            $this->entityManager->getConnection()->executeQuery(
                'SELECT id_nakupu FROM shop_nakupy WHERE id_uzivatele = :user AND rok = :year AND id_predmetu = :product FOR UPDATE',
                [
                    'user'    => $user->getId(),
                    'year'    => $year,
                    'product' => $product->getId(),
                ],
            );

            $item = $this->orderItemRepository->findOneBy([
                'customer' => $user,
                'product'  => $product,
                'year'     => $year,
            ]);

            if ($item === null) {
                $item = new OrderItem();
                $item->setCustomer($user);
                $item->setOrderer($user);
                $item->setProduct($product);
                $item->setVariant($product->getVariants()->first() ?: null);
                $item->setYear($year);
                $item->setProductTags($product->getTagNames());
                // Same snapshot the cart writes, so entry-fee purchases are not the one kind of row
                // whose product name and tags are missing from order history and reports.
                $item->snapshotProduct($product, $item->getVariant());
                $this->entityManager->persist($item);
            }

            $item->setPurchasePrice((string) $amount);
        });

        return $this->getAmount($user);
    }

    /**
     * Last year's average as a percentage of the slider's width, gamma-corrected to match where
     * the handle actually sits for that amount.
     */
    public function lastYearAveragePercent(): int
    {
        // The setting is stored per year; take this year's row rather than the newest, which may
        // already hold a value seeded for a future year. A missing row is not a zero average — it
        // means the value was never computed, so the marker is hidden instead of pinned at 0 %.
        $average = $this->entityManager->getConnection()->fetchOne(
            'SELECT hodnota FROM systemove_nastaveni WHERE klic = :klic AND rocnik_nastaveni = :rocnik LIMIT 1',
            [
                'klic'   => self::LAST_YEAR_AVERAGE_SETTING,
                'rocnik' => $this->currentYearProvider->getCurrentYear(),
            ],
        );

        if ($average === false) {
            return self::AVERAGE_UNKNOWN;
        }

        $ratio = min(1.0, max(0.0, (float) $average / self::SLIDER_MAXIMUM));

        return (int) round($ratio ** self::GAMMA_CORRECTION * 100);
    }

    private function paidForProduct(User $user, Product $product): string
    {
        $sum = $this->entityManager->getConnection()->fetchOne(
            'SELECT COALESCE(SUM(cena_nakupni), 0) FROM shop_nakupy WHERE id_uzivatele = :user AND id_predmetu = :product AND rok = :year',
            [
                'user'    => $user->getId(),
                'product' => $product->getId(),
                'year'    => $this->currentYearProvider->getCurrentYear(),
            ],
        );

        return (string) $sum;
    }

    /**
     * This year's entry-fee product. Past years' ones are archived, so a customer editing their
     * registration can never reach them.
     */
    private function entryFeeProduct(): Product
    {
        foreach ($this->productRepository->findByTag('vstupne') as $product) {
            if ($product->getArchivedAt() === null && ! str_contains($product->getName(), self::LATE_NAME_MARKER)) {
                return $product;
            }
        }

        throw new ConflictHttpException('Dobrovolné vstupné není pro letošní ročník naimportováno.');
    }
}
