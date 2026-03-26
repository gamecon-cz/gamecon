<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\ProductBundle;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\RoleMeaning;
use App\Repository\OrderRepository;
use App\Repository\ProductBundleRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * CartService — business logic for the shopping cart.
 *
 * A "cart" is a pending Order for the current year.
 * Each user has at most one pending order per year.
 */
class CartService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OrderRepository $orderRepository,
        private readonly ProductBundleRepository $bundleRepository,
        private readonly CapacityManager $capacityManager,
        private readonly DiscountCalculator $discountCalculator,
        private readonly CurrentYearProviderInterface $currentYearProvider,
    ) {
    }

    /**
     * Get existing cart or create a new one.
     */
    public function getOrCreateCart(User $user): Order
    {
        $year = $this->currentYearProvider->getCurrentYear();
        $cart = $this->orderRepository->findPendingForCustomer($user, $year);

        if ($cart !== null) {
            return $cart;
        }

        $cart = new Order();
        $cart->setCustomer($user);
        $cart->setYear($year);
        $this->entityManager->persist($cart);
        $this->entityManager->flush();

        return $cart;
    }

    /**
     * Get existing cart or null.
     */
    public function getCart(User $user): ?Order
    {
        $year = $this->currentYearProvider->getCurrentYear();

        return $this->orderRepository->findPendingForCustomer($user, $year);
    }

    /**
     * Add a single variant to the cart.
     *
     * Rejects variants that belong to a forced bundle for the user's roles.
     *
     * @param RoleMeaning[] $roleMeanings User's role meanings for capacity/discount checks
     *
     * @throws \RuntimeException if product unavailable, sold out, or in a forced bundle
     */
    public function addItem(Order $order, ProductVariant $variant, array $roleMeanings = []): OrderItem
    {
        // Guard: reject if variant is in a forced bundle for this user
        $mandatoryBundle = $this->bundleRepository->findMandatoryBundleForVariant($variant, $roleMeanings);
        if ($mandatoryBundle !== null) {
            throw new \RuntimeException(sprintf('Varianta "%s" je součástí povinného balíčku "%s". Použijte nákup celého balíčku.', $variant->getFullName(), $mandatoryBundle->getName()));
        }

        return $this->createOrderItem($order, $variant, null, $roleMeanings);
    }

    /**
     * Add all variants in a bundle to the cart atomically.
     *
     * If any variant fails (sold out), all previously purchased variants
     * are rolled back and the exception is re-thrown.
     *
     * @param RoleMeaning[] $roleMeanings
     *
     * @return OrderItem[]
     *
     * @throws \RuntimeException if any variant is unavailable
     */
    public function addBundle(Order $order, ProductBundle $bundle, array $roleMeanings = []): array
    {
        $variants = $bundle->getVariants()->toArray();
        $purchasedVariants = [];
        $items = [];

        try {
            foreach ($variants as $variant) {
                $product = $variant->getProduct();

                if (! $product->isAvailable()) {
                    throw new \RuntimeException(sprintf('Produkt "%s" není dostupný.', $product->getName()));
                }

                $this->capacityManager->purchase($variant, 1, $roleMeanings);
                $purchasedVariants[] = $variant;

                $items[] = $this->buildOrderItem($order, $variant, $bundle, $roleMeanings);
            }
        } catch (\RuntimeException $e) {
            // Roll back all already-purchased variants
            foreach ($purchasedVariants as $purchased) {
                $this->capacityManager->cancelPurchase($purchased);
            }

            throw $e;
        }

        foreach ($items as $item) {
            $order->addItem($item);
            $this->entityManager->persist($item);
        }

        $order->recalculateTotal();
        $this->entityManager->flush();

        return $items;
    }

    /**
     * Remove a single item from the cart. Returns stock to the variant.
     *
     * Rejects removing items that belong to a forced bundle for the user's roles.
     *
     * @param RoleMeaning[] $roleMeanings
     */
    public function removeItem(Order $order, OrderItem $item, array $roleMeanings = []): void
    {
        $bundle = $item->getBundle();
        if ($bundle !== null && $bundle->isMandatoryForUser($roleMeanings)) {
            throw new \RuntimeException(sprintf('Položka je součástí povinného balíčku "%s". Odeberte celý balíček.', $bundle->getName()));
        }

        $variant = $item->getVariant();

        if ($variant !== null) {
            $this->capacityManager->cancelPurchase($variant);
        }

        $order->removeItem($item);
        $order->recalculateTotal();

        $this->entityManager->remove($item);
        $this->entityManager->flush();
    }

    /**
     * Remove all items belonging to a bundle from the cart.
     */
    public function removeBundle(Order $order, ProductBundle $bundle): void
    {
        $bundleItems = [];
        foreach ($order->getItems() as $item) {
            if ($item->getBundle() === $bundle) {
                $bundleItems[] = $item;
            }
        }

        foreach ($bundleItems as $item) {
            $variant = $item->getVariant();
            if ($variant !== null) {
                $this->capacityManager->cancelPurchase($variant);
            }

            $order->removeItem($item);
            $this->entityManager->remove($item);
        }

        $order->recalculateTotal();
        $this->entityManager->flush();
    }

    /**
     * Create and persist a single order item (used by addItem).
     *
     * @param RoleMeaning[] $roleMeanings
     */
    private function createOrderItem(Order $order, ProductVariant $variant, ?ProductBundle $bundle, array $roleMeanings): OrderItem
    {
        $product = $variant->getProduct();

        if (! $product->isAvailable()) {
            throw new \RuntimeException(sprintf('Produkt "%s" není dostupný.', $product->getName()));
        }

        $this->capacityManager->purchase($variant, 1, $roleMeanings);

        $item = $this->buildOrderItem($order, $variant, $bundle, $roleMeanings);

        $order->addItem($item);
        $order->recalculateTotal();

        $this->entityManager->persist($item);
        $this->entityManager->flush();

        return $item;
    }

    /**
     * Build an OrderItem entity (without persisting or adding to order).
     *
     * @param RoleMeaning[] $roleMeanings
     */
    private function buildOrderItem(Order $order, ProductVariant $variant, ?ProductBundle $bundle, array $roleMeanings): OrderItem
    {
        $product = $variant->getProduct();

        $discountInfo = $this->discountCalculator->calculateDiscount(
            $product,
            $order->getCustomer(),
            $order->getYear(),
        );

        $item = new OrderItem();
        $item->setCustomer($order->getCustomer());
        $item->setProduct($product);
        $item->setVariant($variant);
        $item->setBundle($bundle);
        $item->setYear($order->getYear());
        $item->setOrder($order);
        $item->setPurchasePrice($discountInfo['finalPrice']);
        $item->snapshotProduct($product, $variant);
        $item->setProductTags($product->getTagNames());

        if ($discountInfo['discount'] !== null) {
            $item->setDiscountAmount($discountInfo['discountAmount']);
            $item->setDiscountReason($discountInfo['reason']);
        }

        return $item;
    }
}
