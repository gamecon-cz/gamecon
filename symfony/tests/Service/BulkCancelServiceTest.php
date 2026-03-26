<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\CancelledOrderItem;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Repository\OrderItemRepository;
use App\Service\BulkCancelService;
use App\Service\CapacityManager;
use App\Service\CurrentYearProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BulkCancelServiceTest extends TestCase
{
    private MockObject $entityManager;

    private MockObject $orderItemRepository;

    private MockObject $capacityManager;

    private MockObject $yearProvider;

    private BulkCancelService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->orderItemRepository = $this->createMock(OrderItemRepository::class);
        $this->capacityManager = $this->createMock(CapacityManager::class);
        $this->yearProvider = $this->createMock(CurrentYearProviderInterface::class);
        $this->yearProvider->method('getCurrentYear')->willReturn(2026);

        $this->service = new BulkCancelService(
            $this->entityManager,
            $this->orderItemRepository,
            $this->capacityManager,
            $this->yearProvider,
        );
    }

    public function testCancelAllForUserCancelsAllItems(): void
    {
        $user = $this->createMock(User::class);
        $items = [$this->createItem(), $this->createItem(), $this->createItem()];

        $this->orderItemRepository->method('findByCustomerAndYear')
            ->with($user, 2026)
            ->willReturn($items);

        $this->entityManager->expects($this->exactly(3))
            ->method('remove');

        // 3 archives + flush
        $this->entityManager->expects($this->exactly(3))
            ->method('persist')
            ->with($this->isInstanceOf(CancelledOrderItem::class));

        $count = $this->service->cancelAllForUser(
            $user,
            'hromadne-odhlaseni',
            new \DateTimeImmutable('2026-07-01'),
        );

        $this->assertSame(3, $count);
    }

    public function testCancelAllForUserReturnsZeroWhenNoItems(): void
    {
        $user = $this->createMock(User::class);

        $this->orderItemRepository->method('findByCustomerAndYear')
            ->willReturn([]);

        $this->entityManager->expects($this->never())
            ->method('flush');

        $count = $this->service->cancelAllForUser(
            $user,
            'test',
            new \DateTimeImmutable(),
        );

        $this->assertSame(0, $count);
    }

    public function testCancelByTagForUserFiltersCorrectly(): void
    {
        $user = $this->createMock(User::class);
        $product = $this->createProduct();
        $variant = $this->createVariant($product);

        $ubytovaniItem = $this->createItemWithVariant($variant, ['ubytovani']);
        $jidloItem = $this->createItem(['jidlo']);
        $trickoItem = $this->createItem(['tricko']);

        $this->orderItemRepository->method('findByCustomerAndYear')
            ->willReturn([$ubytovaniItem, $jidloItem, $trickoItem]);

        // Only 1 item matches 'ubytovani' tag
        $this->entityManager->expects($this->once())
            ->method('remove')
            ->with($ubytovaniItem);

        $this->capacityManager->expects($this->once())
            ->method('cancelPurchase')
            ->with($variant);

        $count = $this->service->cancelByTagForUser(
            $user,
            'ubytovani',
            'zruseni-ubytovani',
            new \DateTimeImmutable(),
        );

        $this->assertSame(1, $count);
    }

    public function testCancelByTagForUsersProcessesMultipleUsers(): void
    {
        $user1 = $this->createMock(User::class);
        $user2 = $this->createMock(User::class);

        $this->orderItemRepository->method('findByCustomerAndYear')
            ->willReturnCallback(function (User $user) use ($user1, $user2): array {
                if ($user === $user1) {
                    return [$this->createItem(['ubytovani']), $this->createItem(['ubytovani'])];
                }
                if ($user === $user2) {
                    return [$this->createItem(['ubytovani'])];
                }

                return [];
            });

        $count = $this->service->cancelByTagForUsers(
            [$user1, $user2],
            'ubytovani',
            'hromadne-zruseni',
            new \DateTimeImmutable(),
        );

        $this->assertSame(3, $count);
    }

    public function testCancelOrderCancelsAllItemsAndSetsStatus(): void
    {
        $user = $this->createMock(User::class);
        $order = new Order();
        $order->setCustomer($user);
        $order->setYear(2026);

        $item1 = $this->createItem();
        $item1->setOrder($order);
        $order->addItem($item1);

        $item2 = $this->createItem();
        $item2->setOrder($order);
        $order->addItem($item2);

        $this->entityManager->expects($this->exactly(2))
            ->method('remove');

        $count = $this->service->cancelOrder(
            $order,
            'storno-objednavky',
            new \DateTimeImmutable(),
        );

        $this->assertSame(2, $count);
        $this->assertTrue($order->isCancelled());
        $this->assertTrue($order->isEmpty());
    }

    public function testCancelReturnsStockForVariants(): void
    {
        $user = $this->createMock(User::class);
        $product = $this->createProduct();
        $variant = $this->createVariant($product);

        $item = $this->createItemWithVariant($variant);

        $this->orderItemRepository->method('findByCustomerAndYear')
            ->willReturn([$item]);

        $this->capacityManager->expects($this->once())
            ->method('cancelPurchase')
            ->with($variant);

        $this->service->cancelAllForUser($user, 'test', new \DateTimeImmutable());
    }

    public function testCancelSkipsStockForDeletedVariants(): void
    {
        $user = $this->createMock(User::class);
        $item = $this->createItem(); // no variant

        $this->orderItemRepository->method('findByCustomerAndYear')
            ->willReturn([$item]);

        $this->capacityManager->expects($this->never())
            ->method('cancelPurchase');

        $this->service->cancelAllForUser($user, 'test', new \DateTimeImmutable());
    }

    public function testArchiveRecordHasCorrectData(): void
    {
        $user = $this->createMock(User::class);
        $cancelledAt = new \DateTimeImmutable('2026-07-15 10:00:00');

        $product = $this->createProduct();
        $item = $this->createItem();
        $item->setProduct($product);

        $this->orderItemRepository->method('findByCustomerAndYear')
            ->willReturn([$item]);

        $persisted = [];
        $this->entityManager->method('persist')
            ->willReturnCallback(function ($entity) use (&$persisted): void {
                $persisted[] = $entity;
            });

        $this->service->cancelAllForUser($user, 'automaticke-odhlaseni', $cancelledAt);

        $this->assertCount(1, $persisted);
        $archive = $persisted[0];
        $this->assertInstanceOf(CancelledOrderItem::class, $archive);
        $this->assertSame($product, $archive->getProduct());
        $this->assertSame('250.00', $archive->getPurchasePrice());
        $this->assertSame('automaticke-odhlaseni', $archive->getCancellationReason());
        $this->assertSame($cancelledAt, $archive->getCancelledAt());
    }

    // ==================== Helpers ====================

    /**
     * @param string[] $tags
     */
    private function createItem(array $tags = []): OrderItem
    {
        $item = new OrderItem();
        $item->setCustomer($this->createMock(User::class));
        $item->setProduct($this->createProduct());
        $item->setPurchasePrice('250.00');
        $item->setYear(2026);
        $item->setProductTags($tags);

        return $item;
    }

    /**
     * @param string[] $tags
     */
    private function createItemWithVariant(ProductVariant $variant, array $tags = []): OrderItem
    {
        $item = $this->createItem($tags);
        $item->setVariant($variant);

        return $item;
    }

    private function createProduct(): Product
    {
        $product = new Product();
        $product->setName('Test');
        $product->setCode('TEST');
        $product->setCurrentPrice('250.00');
        $product->setState(1);
        $product->setDescription('');

        return $product;
    }

    private function createVariant(Product $product): ProductVariant
    {
        $variant = new ProductVariant();
        $variant->setProduct($product);
        $variant->setName('Default');
        $variant->setCode('TEST-V');
        $variant->setRemainingQuantity(10);
        $product->addVariant($variant);

        return $variant;
    }
}
