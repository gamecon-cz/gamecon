<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\RoleMeaning;
use App\Repository\OrderRepository;
use App\Service\CapacityManager;
use App\Service\CartService;
use App\Service\CurrentYearProviderInterface;
use App\Service\DiscountCalculator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CartServiceTest extends TestCase
{
    private MockObject $entityManager;

    private MockObject $orderRepository;

    private MockObject $capacityManager;

    private MockObject $discountCalculator;

    private MockObject $yearProvider;

    private CartService $cartService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->orderRepository = $this->createMock(OrderRepository::class);
        $this->capacityManager = $this->createMock(CapacityManager::class);
        $this->discountCalculator = $this->createMock(DiscountCalculator::class);
        $this->yearProvider = $this->createMock(CurrentYearProviderInterface::class);
        $this->yearProvider->method('getCurrentYear')->willReturn(2026);

        $this->cartService = new CartService(
            $this->entityManager,
            $this->orderRepository,
            $this->capacityManager,
            $this->discountCalculator,
            $this->yearProvider,
        );
    }

    public function testGetOrCreateCartReturnsExisting(): void
    {
        $user = $this->createMock(User::class);
        $existingOrder = new Order();
        $existingOrder->setCustomer($user);
        $existingOrder->setYear(2026);

        $this->orderRepository->expects($this->once())
            ->method('findPendingForCustomer')
            ->with($user, 2026)
            ->willReturn($existingOrder);

        $this->entityManager->expects($this->never())
            ->method('persist');

        $cart = $this->cartService->getOrCreateCart($user);
        $this->assertSame($existingOrder, $cart);
    }

    public function testGetOrCreateCartCreatesNew(): void
    {
        $user = $this->createMock(User::class);

        $this->orderRepository->expects($this->once())
            ->method('findPendingForCustomer')
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Order::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $cart = $this->cartService->getOrCreateCart($user);

        $this->assertSame($user, $cart->getCustomer());
        $this->assertSame(2026, $cart->getYear());
        $this->assertTrue($cart->isPending());
    }

    public function testGetCartReturnsNullWhenNone(): void
    {
        $user = $this->createMock(User::class);

        $this->orderRepository->expects($this->once())
            ->method('findPendingForCustomer')
            ->willReturn(null);

        $this->assertNull($this->cartService->getCart($user));
    }

    public function testAddItemSuccess(): void
    {
        $user = $this->createMock(User::class);
        $product = $this->createProduct();
        $variant = $this->createVariant($product, 'M', 'TRICKO-M', 10);

        $order = new Order();
        $order->setCustomer($user);
        $order->setYear(2026);

        $this->discountCalculator->method('calculateDiscount')
            ->willReturn([
                'discount' => null,
                'discountAmount' => '0.00',
                'finalPrice' => '250.00',
                'reason' => null,
            ]);

        $this->capacityManager->expects($this->once())
            ->method('purchase')
            ->with($variant, 1, []);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(OrderItem::class));

        $item = $this->cartService->addItem($order, $variant);

        $this->assertSame($user, $item->getCustomer());
        $this->assertSame($product, $item->getProduct());
        $this->assertSame($variant, $item->getVariant());
        $this->assertSame('250.00', $item->getPurchasePrice());
        $this->assertSame(2026, $item->getYear());
        $this->assertSame('Tričko', $item->getProductName());
        $this->assertSame('M', $item->getVariantName());
    }

    public function testAddItemWithDiscount(): void
    {
        $user = $this->createMock(User::class);
        $product = $this->createProduct();
        $variant = $this->createVariant($product, 'M', 'TRICKO-M', 10);

        $order = new Order();
        $order->setCustomer($user);
        $order->setYear(2026);

        $this->discountCalculator->method('calculateDiscount')
            ->willReturn([
                'discount' => $this->createMock(\App\Entity\ProductDiscount::class),
                'discountAmount' => '250.00',
                'finalPrice' => '0.00',
                'reason' => 'Organizátor (zdarma): 100% sleva',
            ]);

        $roleMeanings = [RoleMeaning::ORGANIZATOR_ZDARMA];

        $item = $this->cartService->addItem($order, $variant, $roleMeanings);

        $this->assertSame('0.00', $item->getPurchasePrice());
        $this->assertSame('250.00', $item->getDiscountAmount());
        $this->assertSame('Organizátor (zdarma): 100% sleva', $item->getDiscountReason());
    }

    public function testAddItemThrowsWhenProductUnavailable(): void
    {
        $product = $this->createProduct();
        $product->setState(0); // MIMO

        $variant = $this->createVariant($product, 'M', 'TRICKO-M', 10);
        $order = new Order();
        $order->setCustomer($this->createMock(User::class));
        $order->setYear(2026);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('není dostupný');

        $this->cartService->addItem($order, $variant);
    }

    public function testAddItemThrowsWhenSoldOut(): void
    {
        $product = $this->createProduct();
        $variant = $this->createVariant($product, 'M', 'TRICKO-M', 0);

        $order = new Order();
        $order->setCustomer($this->createMock(User::class));
        $order->setYear(2026);

        $this->capacityManager->expects($this->once())
            ->method('purchase')
            ->willThrowException(new \RuntimeException('Nedostatečná kapacita'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Nedostatečná kapacita');

        $this->cartService->addItem($order, $variant);
    }

    public function testRemoveItemReturnsStock(): void
    {
        $product = $this->createProduct();
        $variant = $this->createVariant($product, 'M', 'TRICKO-M', 5);

        $item = new OrderItem();
        $item->setProduct($product);
        $item->setVariant($variant);
        $item->setPurchasePrice('250.00');
        $item->setYear(2026);
        $item->setCustomer($this->createMock(User::class));

        $order = new Order();
        $order->setCustomer($this->createMock(User::class));
        $order->setYear(2026);
        $order->addItem($item);
        $item->setOrder($order);

        $this->capacityManager->expects($this->once())
            ->method('cancelPurchase')
            ->with($variant, 1);

        $this->entityManager->expects($this->once())
            ->method('remove')
            ->with($item);

        $this->cartService->removeItem($order, $item);

        $this->assertTrue($order->isEmpty());
    }

    public function testRemoveItemWithDeletedVariant(): void
    {
        $item = new OrderItem();
        $item->setPurchasePrice('100.00');
        $item->setYear(2026);
        $item->setCustomer($this->createMock(User::class));
        // variant is null (deleted)

        $order = new Order();
        $order->setCustomer($this->createMock(User::class));
        $order->setYear(2026);
        $order->addItem($item);
        $item->setOrder($order);

        // Should not call cancelPurchase when variant is null
        $this->capacityManager->expects($this->never())
            ->method('cancelPurchase');

        $this->cartService->removeItem($order, $item);
    }

    public function testAddItemRecalculatesOrderTotal(): void
    {
        $user = $this->createMock(User::class);
        $product = $this->createProduct();
        $variant = $this->createVariant($product, 'M', 'TRICKO-M', 10);

        $order = new Order();
        $order->setCustomer($user);
        $order->setYear(2026);

        $this->discountCalculator->method('calculateDiscount')
            ->willReturn([
                'discount' => null,
                'discountAmount' => '0.00',
                'finalPrice' => '250.00',
                'reason' => null,
            ]);

        $this->cartService->addItem($order, $variant);

        $this->assertSame('250.00', $order->getTotalPrice());
    }

    private function createProduct(): Product
    {
        $product = new Product();
        $product->setName('Tričko');
        $product->setCode('TRICKO');
        $product->setCurrentPrice('250.00');
        $product->setState(1);
        $product->setDescription('');

        return $product;
    }

    private function createVariant(Product $product, string $name, string $code, ?int $remaining): ProductVariant
    {
        $variant = new ProductVariant();
        $variant->setProduct($product);
        $variant->setName($name);
        $variant->setCode($code);
        $variant->setRemainingQuantity($remaining);
        $product->addVariant($variant);

        return $variant;
    }
}
