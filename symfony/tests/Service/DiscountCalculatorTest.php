<?php

declare(strict_types=1);

namespace App\Tests\Service;

use PHPUnit\Framework\MockObject\MockObject;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\OrderItemRepository;
use App\Repository\ProductDiscountRepository;
use App\Service\DiscountCalculator;
use PHPUnit\Framework\TestCase;

class DiscountCalculatorTest extends TestCase
{
    private MockObject $discountRepository;

    private MockObject $orderItemRepository;

    private DiscountCalculator $calculator;

    protected function setUp(): void
    {
        $this->discountRepository = $this->createMock(ProductDiscountRepository::class);
        $this->orderItemRepository = $this->createMock(OrderItemRepository::class);
        $this->calculator = new DiscountCalculator(
            $this->discountRepository,
            $this->orderItemRepository
        );
    }

    public function testCalculateDiscountWithNoUserRoles(): void
    {
        $product = $this->createProduct('100.00');
        $user = $this->createMock(User::class);

        $result = $this->calculator->calculateDiscount($product, $user, 2025);

        $this->assertNull($result['discount']);
        $this->assertSame('0.00', $result['discountAmount']);
        $this->assertSame('100.00', $result['finalPrice']);
        $this->assertNull($result['reason']);
    }

    public function testCalculateDiscountWithNoDiscount(): void
    {
        $product = $this->createProduct('100.00');
        $user = $this->createMock(User::class);

        $this->discountRepository
            ->expects($this->once())
            ->method('findBestDiscountForProduct')
            ->willReturn(null);

        // Note: This test will fail because getUserRoles() is private and returns empty array
        // In real implementation, we need to make it testable or inject a role provider

        $result = $this->calculator->calculateDiscount($product, $user, 2025);

        $this->assertNull($result['discount']);
    }

    public function testIsEligibleForDiscount(): void
    {
        $product = $this->createProduct('100.00');
        $user = $this->createMock(User::class);

        // With no roles/discount
        $eligible = $this->calculator->isEligibleForDiscount($product, $user, 2025);
        $this->assertFalse($eligible);
    }

    private function createProduct(string $price): Product
    {
        $product = new Product();
        $product->setName('Test Product');
        $product->setCode('TEST-001');
        $product->setCurrentPrice($price);
        $product->setState(1);

        return $product;
    }

    /**
     * NOTE: Full testing of DiscountCalculator requires:
     * 1. Extracting getUserRoles() to a separate UserRoleProvider service
     * 2. Mocking that service in tests
     * 3. Testing all discount scenarios (free, percentage, quantity limits)
     *
     * These tests are currently limited due to private getUserRoles() method
     */
}
