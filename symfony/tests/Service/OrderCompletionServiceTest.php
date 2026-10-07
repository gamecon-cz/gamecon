<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\ProductStateEnum;
use App\Service\CurrentYearProviderInterface;
use App\Service\OrderCompletionService;
use App\Structure\Entity\UserEntityStructure;
use App\Tests\AbstractDatabaseKernelTestCase;
use Gamecon\Tests\Factory\UserFactory;

class OrderCompletionServiceTest extends AbstractDatabaseKernelTestCase
{
    public function testCompletesTheCurrentYearCartWithItems(): void
    {
        $customer = $this->createCustomer();
        $cart = $this->createCart($customer, withItem: true);

        $completed = $this->service()->completeCart($customer->getId());

        self::assertTrue($completed);
        self::assertSame(Order::STATUS_COMPLETED, $this->statusInDatabase($cart));
        self::assertNotNull($this->completedAtInDatabase($cart));
    }

    public function testLeavesAnEmptyCartPending(): void
    {
        $customer = $this->createCustomer();
        $cart = $this->createCart($customer, withItem: false);

        $completed = $this->service()->completeCart($customer->getId());

        self::assertFalse($completed);
        self::assertSame(Order::STATUS_PENDING, $this->statusInDatabase($cart));
    }

    public function testDoesNothingWhenThereIsNoCart(): void
    {
        self::assertFalse($this->service()->completeCart($this->createCustomer()->getId()));
    }

    public function testLeavesACartOfAnotherYearAlone(): void
    {
        $customer = $this->createCustomer();
        $cart = $this->createCart($customer, withItem: true, year: $this->currentYear() - 1);

        $completed = $this->service()->completeCart($customer->getId());

        self::assertFalse($completed);
        self::assertSame(Order::STATUS_PENDING, $this->statusInDatabase($cart));
    }

    public function testLeavesTheCartOfAnotherCustomerAlone(): void
    {
        $customer = $this->createCustomer();
        $otherCart = $this->createCart($this->createCustomer(), withItem: true);

        $completed = $this->service()->completeCart($customer->getId());

        self::assertFalse($completed);
        self::assertSame(Order::STATUS_PENDING, $this->statusInDatabase($otherCart));
    }

    public function testCompletingTwiceKeepsTheFirstCompletionTime(): void
    {
        $customer = $this->createCustomer();
        $cart = $this->createCart($customer, withItem: true);
        $this->service()->completeCart($customer->getId());
        $firstCompletion = $this->completedAtInDatabase($cart);

        $completedAgain = $this->service()->completeCart($customer->getId());

        self::assertFalse($completedAgain);
        self::assertSame($firstCompletion, $this->completedAtInDatabase($cart));
    }

    private function service(): OrderCompletionService
    {
        return static::getContainer()->get(OrderCompletionService::class);
    }

    private function currentYear(): int
    {
        return static::getContainer()->get(CurrentYearProviderInterface::class)->getCurrentYear();
    }

    private function createCustomer(): User
    {
        /** @var User $customer */
        $customer = UserFactory::createOne([
            UserEntityStructure::login => 'completion_' . uniqid(),
            UserEntityStructure::email => 'completion_' . uniqid() . '@example.invalid',
        ])->_save()->_real();

        return $customer;
    }

    private function createCart(User $customer, bool $withItem, ?int $year = null): Order
    {
        $year ??= $this->currentYear();

        $cart = new Order();
        $cart->setCustomer($customer);
        $cart->setYear($year);
        $this->entityManager()->persist($cart);

        if ($withItem) {
            $item = new OrderItem();
            $item->setCustomer($customer);
            $item->setOrder($cart);
            $item->setVariant($this->createVariant());
            $item->setYear($year);
            $item->setPurchasePrice('50.00');
            $cart->addItem($item);
            $this->entityManager()->persist($item);
        }

        $this->entityManager()->flush();

        return $cart;
    }

    private function createVariant(): ProductVariant
    {
        $code = 'completion-' . uniqid();

        $product = new Product();
        $product->setName('Tričko');
        $product->setCode($code);
        $product->setCurrentPrice('50.00');
        $product->setDescription('');
        $product->setState(ProductStateEnum::PUBLIC);
        $this->entityManager()->persist($product);

        $variant = new ProductVariant();
        $variant->setProduct($product);
        $variant->setName('M');
        $variant->setCode($code . '-m');
        $variant->setCapacity(10);
        $variant->setPrice('50.00');
        $variant->setPosition(0);
        $product->addVariant($variant);
        $this->entityManager()->persist($variant);

        return $variant;
    }

    private function statusInDatabase(Order $order): string
    {
        return (string) $this->connection()->fetchOne(
            <<<'SQL'
            SELECT status FROM shop_order WHERE id = :id
            SQL,
            [
                'id' => $order->getId(),
            ],
        );
    }

    private function completedAtInDatabase(Order $order): ?string
    {
        $completedAt = $this->connection()->fetchOne(
            <<<'SQL'
            SELECT completed_at FROM shop_order WHERE id = :id
            SQL,
            [
                'id' => $order->getId(),
            ],
        );

        return $completedAt === false ? null : $completedAt;
    }
}
