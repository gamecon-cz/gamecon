<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\RoleMeaning;
use App\Repository\OrderRepository;
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
     * Add a variant to the cart.
     *
     * @param RoleMeaning[] $roleMeanings User's role meanings for capacity/discount checks
     *
     * @throws \RuntimeException if product unavailable or sold out
     */
    public function addItem(Order $order, ProductVariant $variant, array $roleMeanings = []): OrderItem
    {
        $product = $variant->getProduct();

        if (! $product->isAvailable()) {
            throw new \RuntimeException(sprintf('Produkt "%s" není dostupný.', $product->getName()));
        }

        // Atomic stock decrement — throws if sold out
        $this->capacityManager->purchase($variant, 1, $roleMeanings);

        // Calculate discount
        $discountInfo = $this->discountCalculator->calculateDiscount(
            $product,
            $order->getCustomer(),
            $order->getYear(),
        );

        // Create order item
        $item = new OrderItem();
        $item->setCustomer($order->getCustomer());
        $item->setProduct($product);
        $item->setVariant($variant);
        $item->setYear($order->getYear());
        $item->setOrder($order);
        $item->setPurchasePrice($discountInfo['finalPrice']);
        $item->snapshotProduct($product, $variant);
        $item->setProductTags($product->getTagNames());

        if ($discountInfo['discount'] !== null) {
            $item->setDiscountAmount($discountInfo['discountAmount']);
            $item->setDiscountReason($discountInfo['reason']);
        }

        $order->addItem($item);
        $order->recalculateTotal();

        $this->entityManager->persist($item);
        $this->entityManager->flush();

        return $item;
    }

    /**
     * Remove an item from the cart. Returns stock to the variant.
     */
    public function removeItem(Order $order, OrderItem $item): void
    {
        $variant = $item->getVariant();

        if ($variant !== null) {
            $this->capacityManager->cancelPurchase($variant);
        }

        $order->removeItem($item);
        $order->recalculateTotal();

        $this->entityManager->remove($item);
        $this->entityManager->flush();
    }
}
