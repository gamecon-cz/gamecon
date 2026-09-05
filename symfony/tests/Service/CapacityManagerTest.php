<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Enum\RoleMeaning;
use App\Service\CapacityManager;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CapacityManagerTest extends TestCase
{
    private MockObject $connection;

    private MockObject $entityManager;

    private CapacityManager $capacityManager;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->capacityManager = new CapacityManager($this->connection, $this->entityManager);
    }

    public function testUnlimitedCapacityIsAlwaysAvailable(): void
    {
        $variant = $this->createVariant(null);

        $this->assertTrue($this->capacityManager->hasAvailableCapacity($variant));
        $this->assertFalse($this->capacityManager->isSoldOut($variant));
        $this->assertFalse($this->capacityManager->isLowStock($variant, 10));
    }

    public function testHasAvailableCapacityWithStock(): void
    {
        $variant = $this->createVariant(10);

        $this->assertTrue($this->capacityManager->hasAvailableCapacity($variant));
    }

    public function testSoldOutWhenRemainingIsZero(): void
    {
        $variant = $this->createVariant(0);

        $this->assertFalse($this->capacityManager->hasAvailableCapacity($variant));
        $this->assertTrue($this->capacityManager->isSoldOut($variant));
    }

    public function testLowStockDetection(): void
    {
        $variant = $this->createVariant(5);

        $this->assertTrue($this->capacityManager->isLowStock($variant, 10));
        $this->assertFalse($this->capacityManager->isLowStock($variant, 3));
    }

    public function testLowStockFalseWhenSoldOut(): void
    {
        $variant = $this->createVariant(0);

        $this->assertFalse($this->capacityManager->isLowStock($variant, 10));
    }

    public function testOrganizerReservationReducesParticipantAvailability(): void
    {
        $variant = $this->createVariant(10, 3);

        // Participant sees 10 - 3 = 7
        $this->assertTrue($this->capacityManager->hasAvailableCapacity($variant));
        $this->assertSame(7, $variant->getAvailableQuantity([]));

        // Organizer sees all 10
        $orgRoles = [RoleMeaning::ORGANIZATOR_ZDARMA];
        $this->assertTrue($this->capacityManager->hasAvailableCapacity($variant, $orgRoles));
        $this->assertSame(10, $variant->getAvailableQuantity($orgRoles));
    }

    public function testParticipantSoldOutWithReservation(): void
    {
        // 3 remaining, all reserved for organizers
        $variant = $this->createVariant(3, 3);

        $this->assertTrue($this->capacityManager->isSoldOut($variant, []));
        $this->assertFalse($this->capacityManager->isSoldOut($variant, [RoleMeaning::VYPRAVEC]));
    }

    public function testPurchaseRefreshesEntity(): void
    {
        $variant = $this->createVariant(10);

        $this->connection->expects($this->once())
            ->method('executeStatement')
            ->willReturn(1);

        $this->entityManager->expects($this->once())
            ->method('refresh')
            ->with($variant);

        $this->capacityManager->purchase($variant, 1);
    }

    public function testPurchaseSkipsForUnlimitedCapacity(): void
    {
        $variant = $this->createVariant(null);

        $this->connection->expects($this->never())
            ->method('executeStatement');

        $this->capacityManager->purchase($variant, 1);
    }

    public function testPurchaseThrowsWhenSoldOut(): void
    {
        $variant = $this->createVariant(1);

        $this->connection->expects($this->once())
            ->method('executeStatement')
            ->willReturn(0);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Nedostatečná kapacita');

        $this->capacityManager->purchase($variant, 2);
    }

    public function testCancelPurchaseRefreshesEntity(): void
    {
        $variant = $this->createVariant(5);

        $this->connection->expects($this->once())
            ->method('executeStatement')
            ->willReturn(1);

        $this->entityManager->expects($this->once())
            ->method('refresh')
            ->with($variant);

        $this->capacityManager->cancelPurchase($variant, 1);
    }

    public function testCancelPurchaseSkipsForUnlimitedCapacity(): void
    {
        $variant = $this->createVariant(null);

        $this->connection->expects($this->never())
            ->method('executeStatement');

        $this->capacityManager->cancelPurchase($variant, 1);
    }

    public function testGetCapacityInfo(): void
    {
        $variant = $this->createVariant(20, 5);

        $info = $this->capacityManager->getCapacityInfo($variant);

        $this->assertSame(20, $info['remaining']);
        $this->assertSame(5, $info['reserved']);
        $this->assertSame(15, $info['availableForParticipants']);
        $this->assertFalse($info['unlimited']);
    }

    public function testGetCapacityInfoUnlimited(): void
    {
        $variant = $this->createVariant(null);

        $info = $this->capacityManager->getCapacityInfo($variant);

        $this->assertNull($info['remaining']);
        $this->assertSame(0, $info['reserved']);
        $this->assertNull($info['availableForParticipants']);
        $this->assertTrue($info['unlimited']);
    }

    public function testVariousOrganizerRolesHaveAccess(): void
    {
        $variant = $this->createVariant(5, 5);

        // All these should have organizer access
        $this->assertFalse($this->capacityManager->isSoldOut($variant, [RoleMeaning::ORGANIZATOR_ZDARMA]));
        $this->assertFalse($this->capacityManager->isSoldOut($variant, [RoleMeaning::VYPRAVEC]));
        $this->assertFalse($this->capacityManager->isSoldOut($variant, [RoleMeaning::BRIGADNIK]));
        $this->assertFalse($this->capacityManager->isSoldOut($variant, [RoleMeaning::ZAZEMI]));

        // Participant (no org role) should be sold out
        $this->assertTrue($this->capacityManager->isSoldOut($variant, []));
        $this->assertTrue($this->capacityManager->isSoldOut($variant, [RoleMeaning::PRIHLASEN]));
    }

    public function testInheritsReservationFromProduct(): void
    {
        $product = $this->createProduct(10);
        $variant = new ProductVariant();
        $variant->setProduct($product);
        $variant->setName('M');
        $variant->setCode('V-M');
        $variant->setRemainingQuantity(20);
        // reservedForOrganizers = null → inherits 10 from product

        $this->assertSame(10, $variant->getAvailableQuantity([]));
        $this->assertSame(20, $variant->getAvailableQuantity([RoleMeaning::ORGANIZATOR_ZDARMA]));
    }

    private function createProduct(?int $reservedForOrganizers = null): Product
    {
        $product = new Product();
        $product->setName('Test Product');
        $product->setCode('TEST-001');
        $product->setCurrentPrice('100.00');
        $product->setState(1);
        $product->setReservedForOrganizers($reservedForOrganizers);

        return $product;
    }

    private function createVariant(?int $remainingQuantity, ?int $reservedForOrganizers = null): ProductVariant
    {
        $product = $this->createProduct();
        $variant = new ProductVariant();
        $variant->setProduct($product);
        $variant->setName('Test Variant');
        $variant->setCode('TEST-VAR-001');
        $variant->setRemainingQuantity($remainingQuantity);
        $variant->setReservedForOrganizers($reservedForOrganizers);

        return $variant;
    }
}
