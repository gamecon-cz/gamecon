<?php

declare(strict_types=1);

namespace App\Tests\Service;

use PHPUnit\Framework\MockObject\MockObject;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\OrderItemRepository;
use App\Repository\ProductRepository;
use App\Service\CapacityManager;
use PHPUnit\Framework\TestCase;

class CapacityManagerTest extends TestCase
{
    private MockObject $productRepository;

    private MockObject $orderItemRepository;

    private CapacityManager $capacityManager;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepository::class);
        $this->orderItemRepository = $this->createMock(OrderItemRepository::class);
        $this->capacityManager = new CapacityManager(
            $this->productRepository,
            $this->orderItemRepository
        );
    }

    public function testGetAvailableCapacityWithUnlimitedCapacity(): void
    {
        $product = $this->createProduct(null);

        $available = $this->capacityManager->getAvailableCapacity($product, 2025);

        $this->assertSame(PHP_INT_MAX, $available);
    }

    public function testGetAvailableCapacityWithProducedQuantity(): void
    {
        $product = $this->createProduct(100);

        $this->orderItemRepository
            ->expects($this->once())
            ->method('countByProductAndYear')
            ->with($product, 2025)
            ->willReturn(30);

        $available = $this->capacityManager->getAvailableCapacity($product, 2025);

        $this->assertSame(70, $available); // 100 - 30 = 70
    }

    public function testGetAvailableCapacityWhenSoldOut(): void
    {
        $product = $this->createProduct(50);

        $this->orderItemRepository
            ->expects($this->once())
            ->method('countByProductAndYear')
            ->willReturn(50);

        $available = $this->capacityManager->getAvailableCapacity($product, 2025);

        $this->assertSame(0, $available);
    }

    public function testGetAvailableCapacityWhenOversold(): void
    {
        $product = $this->createProduct(50);

        $this->orderItemRepository
            ->expects($this->once())
            ->method('countByProductAndYear')
            ->willReturn(60); // Oversold

        $available = $this->capacityManager->getAvailableCapacity($product, 2025);

        $this->assertSame(0, $available); // Should return 0, not negative
    }

    public function testHasAvailableCapacity(): void
    {
        $product = $this->createProduct(50);
        $user = $this->createMock(User::class);

        $this->orderItemRepository
            ->method('countByProductAndYear')
            ->willReturn(30);

        $hasCapacity = $this->capacityManager->hasAvailableCapacity($product, $user, 2025);

        $this->assertTrue($hasCapacity);
    }

    public function testIsSoldOut(): void
    {
        $product = $this->createProduct(50);

        $this->orderItemRepository
            ->method('countByProductAndYear')
            ->willReturn(50);

        $soldOut = $this->capacityManager->isSoldOut($product, 2025);

        $this->assertTrue($soldOut);
    }

    public function testIsLowStock(): void
    {
        $product = $this->createProduct(50);

        // 5 items remaining (threshold = 10)
        $this->orderItemRepository
            ->method('countByProductAndYear')
            ->willReturn(45);

        $lowStock = $this->capacityManager->isLowStock($product, 2025, 10);

        $this->assertTrue($lowStock);
    }

    public function testIsNotLowStockWithEnoughCapacity(): void
    {
        $product = $this->createProduct(50);

        // 30 items remaining (threshold = 10)
        $this->orderItemRepository
            ->method('countByProductAndYear')
            ->willReturn(20);

        $lowStock = $this->capacityManager->isLowStock($product, 2025, 10);

        $this->assertFalse($lowStock);
    }

    public function testValidateCapacityThrowsExceptionWhenExceeded(): void
    {
        $product = $this->createProduct(50);
        $user = $this->createMock(User::class);

        $this->orderItemRepository
            ->method('countByProductAndYear')
            ->willReturn(48); // Only 2 remaining

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Nedostatečná kapacita');

        $this->capacityManager->validateCapacity($product, $user, 2025, 5); // Want 5, only 2 available
    }

    public function testValidateCapacitySucceedsWhenEnoughAvailable(): void
    {
        $product = $this->createProduct(50);
        $user = $this->createMock(User::class);

        $this->orderItemRepository
            ->method('countByProductAndYear')
            ->willReturn(30); // 20 remaining

        // Should not throw
        $this->capacityManager->validateCapacity($product, $user, 2025, 10);

        $this->assertTrue(true); // If we reach here, validation passed
    }

    public function testGetCapacityInfo(): void
    {
        $product = $this->createProduct(100);

        $this->orderItemRepository
            ->method('countByProductAndYear')
            ->willReturn(30);

        $info = $this->capacityManager->getCapacityInfo($product, 2025);

        $this->assertSame(100, $info['total']);
        $this->assertSame(30, $info['sold']);
        $this->assertSame(70, $info['available']);
        $this->assertSame(30.0, $info['percentSold']);
    }

    public function testGetCapacityInfoWithUnlimitedCapacity(): void
    {
        $product = $this->createProduct(null);

        $this->orderItemRepository
            ->method('countByProductAndYear')
            ->willReturn(50);

        $info = $this->capacityManager->getCapacityInfo($product, 2025);

        $this->assertNull($info['total']);
        $this->assertSame(50, $info['sold']);
        $this->assertSame(PHP_INT_MAX, $info['available']);
        $this->assertSame(0.0, $info['percentSold']);
    }

    private function createProduct(?int $producedQuantity): Product
    {
        $product = new Product();
        $product->setName('Test Product');
        $product->setCode('TEST-001');
        $product->setCurrentPrice('100.00');
        $product->setState(1);
        $product->setProducedQuantity($producedQuantity);

        return $product;
    }
}
