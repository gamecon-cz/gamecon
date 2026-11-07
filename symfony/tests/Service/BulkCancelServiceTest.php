<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\CancelledOrderItem;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\ProductStateEnum;
use App\Repository\OrderItemRepository;
use App\Service\BulkCancelService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BulkCancelServiceTest extends TestCase
{
    private MockObject $entityManager;

    private MockObject $orderItemRepository;

    private BulkCancelService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->orderItemRepository = $this->createMock(OrderItemRepository::class);

        $this->service = new BulkCancelService(
            $this->entityManager,
            $this->orderItemRepository,
        );
    }

    public function testCancelPurchasesArchivesAndRemovesEachPurchase(): void
    {
        $items = [$this->createItem(), $this->createItem(), $this->createItem()];

        $this->orderItemRepository->method('findBy')
            ->with([
                'id' => [1, 2, 3],
            ], [
                'id' => 'ASC',
            ])
            ->willReturn($items);

        $this->entityManager->expects($this->exactly(3))
            ->method('remove');

        $this->entityManager->expects($this->exactly(3))
            ->method('persist')
            ->with($this->isInstanceOf(CancelledOrderItem::class));

        $count = $this->service->cancelPurchases(
            [1, 2, 3],
            'hromadne-odhlaseni',
            new \DateTimeImmutable('2026-07-01'),
        );

        $this->assertSame(3, $count);
    }

    public function testCancelPurchasesReturnsZeroWhenNoPurchaseMatches(): void
    {
        $this->orderItemRepository->method('findBy')
            ->willReturn([]);

        $this->entityManager->expects($this->never())
            ->method('flush');

        $count = $this->service->cancelPurchases(
            [5],
            'test',
            new \DateTimeImmutable(),
        );

        $this->assertSame(0, $count);
    }

    public function testCancelPurchasesTakesThePurchaseOutOfItsOrderAndRecalculatesTheTotal(): void
    {
        $order = new Order();
        $order->setCustomer($this->createMock(User::class));
        $order->setYear(2026);

        $cancelled = $this->createItem();
        $kept = $this->createItem();
        foreach ([$cancelled, $kept] as $item) {
            $item->setOrder($order);
            $order->addItem($item);
        }
        $order->recalculateTotal();
        $this->assertSame('500.00', $order->getTotalPrice());

        $this->orderItemRepository->method('findBy')
            ->willReturn([$cancelled]);

        $this->service->cancelPurchases([1], 'test', new \DateTimeImmutable());

        $this->assertSame(1, $order->getItemCount());
        $this->assertSame('250.00', $order->getTotalPrice());
    }

    public function testCancelPurchasesWithoutIdsDoesNotQuery(): void
    {
        $this->orderItemRepository->expects($this->never())
            ->method('findBy');

        $count = $this->service->cancelPurchases(
            [],
            'test',
            new \DateTimeImmutable(),
        );

        $this->assertSame(0, $count);
    }

    public function testArchiveRecordHasCorrectData(): void
    {
        $cancelledAt = new \DateTimeImmutable('2026-07-15 10:00:00');

        $product = $this->createProduct();
        $variant = $this->createVariant($product);
        $item = $this->createItemWithVariant($variant);

        $this->orderItemRepository->method('findBy')
            ->willReturn([$item]);

        $persisted = [];
        $this->entityManager->method('persist')
            ->willReturnCallback(function ($entity) use (&$persisted): void {
                $persisted[] = $entity;
            });

        $this->service->cancelPurchases([1], 'automaticke-odhlaseni', $cancelledAt);

        $this->assertCount(1, $persisted);
        $archive = $persisted[0];
        $this->assertInstanceOf(CancelledOrderItem::class, $archive);
        $this->assertSame($product, $archive->getProduct());
        $this->assertSame($variant, $archive->getVariant(), 'The size or night that was bought');
        $this->assertSame('250.00', $archive->getPurchasePrice());
        $this->assertSame('automaticke-odhlaseni', $archive->getCancellationReason());
        $this->assertSame($cancelledAt, $archive->getCancelledAt());
    }

    // ==================== Helpers ====================

    private function createItem(): OrderItem
    {
        $product = $this->createProduct();
        $item = new OrderItem();
        $item->setCustomer($this->createMock(User::class));
        // Every purchase names its variant; the database requires it.
        $item->setVariant($this->createVariant($product));
        $item->setPurchasePrice('250.00');
        $item->setYear(2026);

        return $item;
    }

    private function createItemWithVariant(ProductVariant $variant): OrderItem
    {
        $item = $this->createItem();
        $item->setVariant($variant);

        return $item;
    }

    private function createProduct(): Product
    {
        $product = new Product();
        $product->setName('Test');
        $product->setCode('TEST');
        $product->setCurrentPrice('250.00');
        $product->setState(ProductStateEnum::PUBLIC);
        $product->setDescription('');

        return $product;
    }

    private function createVariant(Product $product): ProductVariant
    {
        $variant = new ProductVariant();
        $variant->setProduct($product);
        $variant->setName('Default');
        $variant->setCode('TEST-V');
        $product->addVariant($variant);

        return $variant;
    }
}
