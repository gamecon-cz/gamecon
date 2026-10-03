<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\ProductBundle;
use App\Entity\ProductTag;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\ProductStateEnum;
use App\Enum\ProductTagCode;
use App\Enum\RoleMeaning;
use App\Exception\CapacityExceededException;
use App\Exception\InvalidRequestException;
use App\Exception\NoLongerAvailableException;
use App\Repository\OrderItemRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductBundleRepository;
use App\Service\CapacityManager;
use App\Service\CartService;
use App\Service\CurrentYearProviderInterface;
use App\Service\DiscountCalculator;
use App\Service\RestrictedProductRules;
use App\Service\SpentQuotaProvider;
use App\Tests\Support\ChybovePreklady;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

class CartServiceTest extends TestCase
{
    private MockObject $entityManager;

    private MockObject $orderRepository;

    private MockObject $bundleRepository;

    private MockObject $capacityManager;

    private MockObject $discountCalculator;

    private MockObject $yearProvider;

    private MockObject $restrictedProductRules;

    private MockObject $orderItemRepository;

    private CartService $cartService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->orderRepository = $this->createMock(OrderRepository::class);
        $this->bundleRepository = $this->createMock(ProductBundleRepository::class);
        $this->capacityManager = $this->createMock(CapacityManager::class);
        $this->discountCalculator = $this->createMock(DiscountCalculator::class);
        $this->yearProvider = $this->createMock(CurrentYearProviderInterface::class);
        $this->yearProvider->method('getCurrentYear')->willReturn(2026);
        $this->restrictedProductRules = $this->createMock(RestrictedProductRules::class);
        $this->orderItemRepository = $this->createMock(OrderItemRepository::class);

        $this->cartService = new CartService(
            $this->entityManager,
            $this->orderRepository,
            $this->bundleRepository,
            $this->capacityManager,
            $this->discountCalculator,
            $this->yearProvider,
            new MockClock('2026-09-12 10:00:00'),
            $this->restrictedProductRules,
            $this->orderItemRepository,
            $this->createMock(SpentQuotaProvider::class),
            ChybovePreklady::translator(),
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
        $variant = $this->createVariant($product, 'M', 'TRICKO-M');

        $order = new Order();
        $order->setCustomer($user);
        $order->setYear(2026);

        $this->discountCalculator->method('priceForNextPiece')
            ->willReturn([
                'discount'       => null,
                'discountAmount' => '0.00',
                'finalPrice'     => '250.00',
                'reason'         => null,
                'snapshot'       => null,
            ]);

        $this->capacityManager->expects($this->once())
            ->method('lockForSale')
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
        $variant = $this->createVariant($product, 'M', 'TRICKO-M');

        $order = new Order();
        $order->setCustomer($user);
        $order->setYear(2026);

        $this->discountCalculator->method('priceForNextPiece')
            ->willReturn([
                'discount'       => $this->createMock(\App\Entity\ProductDiscount::class),
                'discountAmount' => '250.00',
                'finalPrice'     => '0.00',
                'reason'         => 'Organizátor (zdarma): 100% sleva',
                'snapshot'       => null,
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
        $product->setState(ProductStateEnum::RETIRED);

        $variant = $this->createVariant($product, 'M', 'TRICKO-M');
        $order = new Order();
        $order->setCustomer($this->createMock(User::class));
        $order->setYear(2026);

        $this->expectException(NoLongerAvailableException::class);
        $this->expectExceptionMessage('není dostupný');

        $this->cartService->addItem($order, $variant);
    }

    public function testAddItemRefusesAnAccommodationNight(): void
    {
        $typPokoje = $this->createProduct();
        $typPokoje->addTag((new ProductTag())->setCode(ProductTagCode::UBYTOVANI->value));
        $noc = $this->createVariant($typPokoje, 'pátek', 'POSTEL-PA');
        $noc->setAccommodationDay(2);
        $order = new Order();
        $order->setCustomer($this->createMock(User::class));
        $order->setYear(2026);

        $this->capacityManager->expects($this->never())->method('lockForSale');
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Ubytování se objednává');

        $this->cartService->addItem($order, $noc);
    }

    public function testAddItemThrowsWhenSoldOut(): void
    {
        $product = $this->createProduct();
        $variant = $this->createVariant($product, 'M', 'TRICKO-M');

        $order = new Order();
        $order->setCustomer($this->createMock(User::class));
        $order->setYear(2026);

        $this->capacityManager->expects($this->once())
            ->method('lockForSale')
            ->willThrowException(new CapacityExceededException('Nedostatečná kapacita'));

        $this->expectException(CapacityExceededException::class);
        $this->expectExceptionMessage('Nedostatečná kapacita');

        $this->cartService->addItem($order, $variant);
    }

    public function testRemoveItemDeletesTheRow(): void
    {
        $product = $this->createProduct();
        $variant = $this->createVariant($product, 'M', 'TRICKO-M');

        $item = new OrderItem();
        $item->setVariant($variant);
        $item->setPurchasePrice('250.00');
        $item->setYear(2026);
        $item->setCustomer($this->createMock(User::class));

        $order = new Order();
        $order->setCustomer($this->createMock(User::class));
        $order->setYear(2026);
        $order->addItem($item);
        $item->setOrder($order);

        $this->entityManager->expects($this->once())
            ->method('remove')
            ->with($item);

        $this->cartService->removeItem($order, $item);

        $this->assertTrue($order->isEmpty());
    }

    public function testAddItemRecalculatesOrderTotal(): void
    {
        $user = $this->createMock(User::class);
        $product = $this->createProduct();
        $variant = $this->createVariant($product, 'M', 'TRICKO-M');

        $order = new Order();
        $order->setCustomer($user);
        $order->setYear(2026);

        $this->discountCalculator->method('priceForNextPiece')
            ->willReturn([
                'discount'       => null,
                'discountAmount' => '0.00',
                'finalPrice'     => '250.00',
                'reason'         => null,
                'snapshot'       => null,
            ]);

        $this->cartService->addItem($order, $variant);

        $this->assertSame('250.00', $order->getTotalPrice());
    }

    // ==================== Bundle Tests ====================

    public function testAddItemRejectsVariantInForcedBundle(): void
    {
        $product = $this->createProduct();
        $variant = $this->createVariant($product, 'Thu', 'ACCOM-THU');
        $bundle = $this->createBundle('Víkendový balíček', true, [RoleMeaning::PRIHLASEN->value]);

        $order = new Order();
        $order->setCustomer($this->createMock(User::class));
        $order->setYear(2026);

        $this->bundleRepository->method('findMandatoryBundleForVariant')
            ->willReturn($bundle);

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('povinného balíčku');

        $this->cartService->addItem($order, $variant, [RoleMeaning::PRIHLASEN]);
    }

    public function testAddItemAllowsVariantWhenBundleNotMandatory(): void
    {
        $product = $this->createProduct();
        $variant = $this->createVariant($product, 'Thu', 'ACCOM-THU');

        $order = new Order();
        $order->setCustomer($this->createMock(User::class));
        $order->setYear(2026);

        // Organizer — no mandatory bundle
        $this->bundleRepository->method('findMandatoryBundleForVariant')
            ->willReturn(null);

        $this->discountCalculator->method('priceForNextPiece')
            ->willReturn([
                'discount'       => null,
                'discountAmount' => '0.00',
                'finalPrice'     => '250.00',
                'reason'         => null,
                'snapshot'       => null,
            ]);

        $item = $this->cartService->addItem($order, $variant, [RoleMeaning::ORGANIZATOR_ZDARMA]);

        $this->assertSame($variant, $item->getVariant());
    }

    public function testAddBundleRefusesAnAccommodationNight(): void
    {
        $typPokoje = $this->createProduct();
        $typPokoje->addTag((new ProductTag())->setCode(ProductTagCode::UBYTOVANI->value));
        $bundle = $this->createBundle('Víkend', false, [RoleMeaning::PRIHLASEN->value]);
        $bundle->addVariant($this->createVariant($typPokoje, 'pátek', 'POSTEL-PA'));
        $order = new Order();
        $order->setCustomer($this->createMock(User::class));
        $order->setYear(2026);

        $this->capacityManager->expects($this->never())->method('lockInOrder');
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Ubytování se objednává');

        $this->cartService->addBundle($order, $bundle, [RoleMeaning::PRIHLASEN]);
    }

    public function testAddBundleAddsAllVariants(): void
    {
        $product = $this->createProduct();
        $v1 = $this->createVariant($product, 'Thu', 'ACCOM-THU');
        $v2 = $this->createVariant($product, 'Fri', 'ACCOM-FRI');
        $v3 = $this->createVariant($product, 'Sat', 'ACCOM-SAT');

        $bundle = $this->createBundle('Víkend', true, [RoleMeaning::PRIHLASEN->value]);
        $bundle->addVariant($v1);
        $bundle->addVariant($v2);
        $bundle->addVariant($v3);

        $order = new Order();
        $order->setCustomer($this->createMock(User::class));
        $order->setYear(2026);

        $this->capacityManager->expects($this->exactly(3))
            ->method('lockForSale');

        $this->discountCalculator->method('priceForNextPiece')
            ->willReturn([
                'discount'       => null,
                'discountAmount' => '0.00',
                'finalPrice'     => '250.00',
                'reason'         => null,
                'snapshot'       => null,
            ]);

        $items = $this->cartService->addBundle($order, $bundle, [RoleMeaning::PRIHLASEN]);

        $this->assertCount(3, $items);
        $this->assertSame(3, $order->getItemCount());
        foreach ($items as $item) {
            $this->assertSame($bundle, $item->getBundle());
        }
    }

    public function testAddBundleLocksAllItsVariantsBeforeCheckingAny(): void
    {
        $product = $this->createProduct();
        $bundle = $this->createBundle('Víkend', true, [RoleMeaning::PRIHLASEN->value]);
        foreach ([
            30 => 'ACCOM-SAT',
            10 => 'ACCOM-THU',
            20 => 'ACCOM-FRI',
        ] as $id => $code) {
            $variant = $this->createVariant($product, $code, $code);
            (new \ReflectionProperty(ProductVariant::class, 'id'))->setValue($variant, $id);
            $bundle->addVariant($variant);
        }

        $order = new Order();
        $order->setCustomer($this->createMock(User::class));
        $order->setYear(2026);

        $this->discountCalculator->method('priceForNextPiece')
            ->willReturn([
                'discount'       => null,
                'discountAmount' => '0.00',
                'finalPrice'     => '250.00',
                'reason'         => null,
                'snapshot'       => null,
            ]);

        $calls = [];
        $this->capacityManager->method('lockInOrder')
            ->willReturnCallback(function (array $variantIds) use (&$calls): void {
                $calls[] = ['lockInOrder', $variantIds];
            });
        $this->capacityManager->method('lockForSale')
            ->willReturnCallback(function (ProductVariant $variant) use (&$calls): void {
                $calls[] = ['lockForSale', $variant->getId()];
            });

        $this->cartService->addBundle($order, $bundle, [RoleMeaning::PRIHLASEN]);

        $this->assertSame(['lockInOrder', [30, 10, 20]], $calls[0]);
        $this->assertCount(4, $calls);
    }

    public function testAddBundleAddsNothingWhenOneVariantIsSoldOut(): void
    {
        $product = $this->createProduct();
        $v1 = $this->createVariant($product, 'Thu', 'ACCOM-THU');
        $v2 = $this->createVariant($product, 'Fri', 'ACCOM-FRI');

        $bundle = $this->createBundle('Víkend', true, [RoleMeaning::PRIHLASEN->value]);
        $bundle->addVariant($v1);
        $bundle->addVariant($v2);

        $order = new Order();
        $order->setCustomer($this->createMock(User::class));
        $order->setYear(2026);

        $this->discountCalculator->method('priceForNextPiece')
            ->willReturn([
                'discount'       => null,
                'discountAmount' => '0.00',
                'finalPrice'     => '250.00',
                'reason'         => null,
                'snapshot'       => null,
            ]);

        $callCount = 0;
        $this->capacityManager->method('lockForSale')
            ->willReturnCallback(function () use (&$callCount): void {
                ++$callCount;
                if ($callCount === 2) {
                    throw new CapacityExceededException('Nedostatečná kapacita');
                }
            });

        $this->entityManager->expects($this->never())
            ->method('persist');

        $this->expectException(CapacityExceededException::class);
        $this->expectExceptionMessage('Nedostatečná kapacita');

        $this->cartService->addBundle($order, $bundle, [RoleMeaning::PRIHLASEN]);
    }

    public function testRemoveItemRejectsItemInForcedBundle(): void
    {
        $bundle = $this->createBundle('Víkend', true, [RoleMeaning::PRIHLASEN->value]);
        $product = $this->createProduct();
        $variant = $this->createVariant($product, 'Thu', 'ACCOM-THU');

        $item = new OrderItem();
        $item->setVariant($variant);
        $item->setBundle($bundle);
        $item->setPurchasePrice('250.00');
        $item->setYear(2026);
        $item->setCustomer($this->createMock(User::class));

        $order = new Order();
        $order->setCustomer($this->createMock(User::class));
        $order->setYear(2026);
        $order->addItem($item);
        $item->setOrder($order);

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('povinného balíčku');

        $this->cartService->removeItem($order, $item, [RoleMeaning::PRIHLASEN]);
    }

    public function testRemoveBundleRemovesAllItems(): void
    {
        $bundle = $this->createBundle('Víkend', true, [RoleMeaning::PRIHLASEN->value]);
        $product = $this->createProduct();
        $v1 = $this->createVariant($product, 'Thu', 'ACCOM-THU');
        $v2 = $this->createVariant($product, 'Fri', 'ACCOM-FRI');

        $item1 = new OrderItem();
        $item1->setVariant($v1);
        $item1->setBundle($bundle);
        $item1->setPurchasePrice('250.00');
        $item1->setYear(2026);
        $item1->setCustomer($this->createMock(User::class));

        $item2 = new OrderItem();
        $item2->setVariant($v2);
        $item2->setBundle($bundle);
        $item2->setPurchasePrice('250.00');
        $item2->setYear(2026);
        $item2->setCustomer($this->createMock(User::class));

        $order = new Order();
        $order->setCustomer($this->createMock(User::class));
        $order->setYear(2026);
        $order->addItem($item1);
        $order->addItem($item2);
        $item1->setOrder($order);
        $item2->setOrder($order);

        $this->entityManager->expects($this->exactly(2))
            ->method('remove');

        $this->cartService->removeBundle($order, $bundle);

        $this->assertTrue($order->isEmpty());
    }

    private function createBundle(string $name, bool $forced, array $roles): ProductBundle
    {
        $bundle = new ProductBundle();
        $bundle->setName($name);
        $bundle->setForced($forced);
        $bundle->setApplicableToRoles($roles);

        return $bundle;
    }

    private function createProduct(): Product
    {
        $product = new Product();
        $product->setName('Tričko');
        $product->setCode('TRICKO');
        $product->setCurrentPrice('250.00');
        $product->setState(ProductStateEnum::PUBLIC);
        $product->setDescription('');

        return $product;
    }

    private function createVariant(Product $product, string $name, string $code): ProductVariant
    {
        $variant = new ProductVariant();
        $variant->setProduct($product);
        $variant->setName($name);
        $variant->setCode($code);
        $product->addVariant($variant);

        return $variant;
    }
}
