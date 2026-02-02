<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\CancelledOrderItem;
use App\Entity\Product;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class CancelledOrderItemTest extends TestCase
{
    private CancelledOrderItem $cancelledOrderItem;

    protected function setUp(): void
    {
        $this->cancelledOrderItem = new CancelledOrderItem();
    }

    public function testConstructorInitializesCancelledAt(): void
    {
        $this->assertInstanceOf(\DateTimeInterface::class, $this->cancelledOrderItem->getCancelledAt());
    }

    public function testSettersAndGetters(): void
    {
        $this->cancelledOrderItem->setId(123);
        $this->assertSame(123, $this->cancelledOrderItem->getId());

        $this->cancelledOrderItem->setYear(2024);
        $this->assertSame(2024, $this->cancelledOrderItem->getYear());

        $this->cancelledOrderItem->setPurchasePrice('50.00');
        $this->assertSame('50.00', $this->cancelledOrderItem->getPurchasePrice());

        $purchasedAt = new \DateTime('2024-01-15');
        $this->cancelledOrderItem->setPurchasedAt($purchasedAt);
        $this->assertSame($purchasedAt, $this->cancelledOrderItem->getPurchasedAt());

        $cancelledAt = new \DateTime('2024-02-01');
        $this->cancelledOrderItem->setCancelledAt($cancelledAt);
        $this->assertSame($cancelledAt, $this->cancelledOrderItem->getCancelledAt());

        $this->cancelledOrderItem->setCancellationReason('User requested refund');
        $this->assertSame('User requested refund', $this->cancelledOrderItem->getCancellationReason());
    }

    public function testCustomerRelationship(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getCelemeJmeno')->willReturn('Jan Novák');

        $this->cancelledOrderItem->setCustomer($user);
        $this->assertSame($user, $this->cancelledOrderItem->getCustomer());
    }

    public function testProductRelationship(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getName')->willReturn('Test Product');

        $this->cancelledOrderItem->setProduct($product);
        $this->assertSame($product, $this->cancelledOrderItem->getProduct());
    }

    public function testGetDisplayProductNameWithProduct(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getName')->willReturn('GameCon kostka');

        $this->cancelledOrderItem->setProduct($product);

        $this->assertSame('GameCon kostka', $this->cancelledOrderItem->getDisplayProductName());
    }

    public function testGetDisplayProductNameWithoutProduct(): void
    {
        // No product set (deleted product scenario)
        $this->cancelledOrderItem->setProduct(null);

        $this->assertSame('Smazaný produkt', $this->cancelledOrderItem->getDisplayProductName());
    }

    public function testCancellationReasonCanBeNull(): void
    {
        $this->assertNull($this->cancelledOrderItem->getCancellationReason());

        $this->cancelledOrderItem->setCancellationReason('Admin cancelled');
        $this->assertSame('Admin cancelled', $this->cancelledOrderItem->getCancellationReason());

        $this->cancelledOrderItem->setCancellationReason(null);
        $this->assertNull($this->cancelledOrderItem->getCancellationReason());
    }
}
