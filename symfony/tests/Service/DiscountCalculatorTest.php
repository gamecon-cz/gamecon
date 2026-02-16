<?php

declare(strict_types=1);

namespace App\Tests\Service;

use PHPUnit\Framework\MockObject\MockObject;
use App\Entity\Product;
use App\Entity\ProductDiscount;
use App\Entity\Role;
use App\Entity\User;
use App\Entity\UserRole;
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
        $user = new User(); // Real user with no roles

        $result = $this->calculator->calculateDiscount($product, $user, 2025);

        $this->assertNull($result['discount']);
        $this->assertSame('0.00', $result['discountAmount']);
        $this->assertSame('100.00', $result['finalPrice']);
        $this->assertNull($result['reason']);
    }

    public function testCalculateDiscountWithNoDiscount(): void
    {
        $product = $this->createProduct('100.00');
        $user = $this->createUserWithRoles(['organizator']);

        $this->discountRepository
            ->expects($this->once())
            ->method('findBestDiscountForProduct')
            ->with($product, ['organizator'])
            ->willReturn(null);

        $result = $this->calculator->calculateDiscount($product, $user, 2025);

        $this->assertNull($result['discount']);
        $this->assertSame('0.00', $result['discountAmount']);
        $this->assertSame('100.00', $result['finalPrice']);
    }

    public function testIsEligibleForDiscount(): void
    {
        $product = $this->createProduct('100.00');
        $user = new User(); // No roles

        // With no roles/discount
        $eligible = $this->calculator->isEligibleForDiscount($product, $user, 2025);
        $this->assertFalse($eligible);
    }

    public function testCalculateDiscountWithPercentageDiscount(): void
    {
        $product = $this->createProduct('100.00');
        $user = $this->createUserWithRoles(['vypravec']);

        $discount = new ProductDiscount();
        $discount->setProduct($product);
        $discount->setRole('vypravec');
        $discount->setDiscountPercent('20.00');

        $this->discountRepository
            ->expects($this->once())
            ->method('findBestDiscountForProduct')
            ->with($product, ['vypravec'])
            ->willReturn($discount);

        $result = $this->calculator->calculateDiscount($product, $user, 2025);

        $this->assertSame($discount, $result['discount']);
        $this->assertSame('20.00', $result['discountAmount']);
        $this->assertSame('80.00', $result['finalPrice']);
        $this->assertStringContainsString('20', $result['reason']);
    }

    public function testCalculateDiscountWithMultipleRoles(): void
    {
        $product = $this->createProduct('100.00');
        $user = $this->createUserWithRoles(['organizator', 'vypravec']);

        $discount = new ProductDiscount();
        $discount->setProduct($product);
        $discount->setRole('organizator');
        $discount->setDiscountPercent('30.00');

        $this->discountRepository
            ->expects($this->once())
            ->method('findBestDiscountForProduct')
            ->with($product, ['organizator', 'vypravec'])
            ->willReturn($discount);

        $result = $this->calculator->calculateDiscount($product, $user, 2025);

        $this->assertSame($discount, $result['discount']);
        $this->assertSame('30.00', $result['discountAmount']);
        $this->assertSame('70.00', $result['finalPrice']);
    }

    public function testCalculateDiscountWithQuantityLimit(): void
    {
        $product = $this->createProduct('100.00');
        $user = $this->createUserWithRoles(['organizator']);

        $discount = new ProductDiscount();
        $discount->setProduct($product);
        $discount->setRole('organizator');
        $discount->setDiscountPercent('25.00');
        $discount->setMaxQuantity(2);

        $this->discountRepository
            ->expects($this->once())
            ->method('findBestDiscountForProduct')
            ->with($product, ['organizator'])
            ->willReturn($discount);

        $this->orderItemRepository
            ->expects($this->once())
            ->method('countCustomerPurchases')
            ->with($user, $product, 2025)
            ->willReturn(0);

        $result = $this->calculator->calculateDiscount($product, $user, 2025);

        $this->assertSame($discount, $result['discount']);
        $this->assertSame('25.00', $result['discountAmount']);
    }

    public function testCalculateDiscountWithExceededQuantityLimit(): void
    {
        $product = $this->createProduct('100.00');
        $user = $this->createUserWithRoles(['organizator']);

        $discount = new ProductDiscount();
        $discount->setProduct($product);
        $discount->setRole('organizator');
        $discount->setDiscountPercent('25.00');
        $discount->setMaxQuantity(2);

        $this->discountRepository
            ->expects($this->once())
            ->method('findBestDiscountForProduct')
            ->with($product, ['organizator'])
            ->willReturn($discount);

        $this->orderItemRepository
            ->expects($this->once())
            ->method('countCustomerPurchases')
            ->with($user, $product, 2025)
            ->willReturn(2); // Already purchased 2

        $result = $this->calculator->calculateDiscount($product, $user, 2025);

        $this->assertNull($result['discount']);
        $this->assertSame('0.00', $result['discountAmount']);
        $this->assertSame('100.00', $result['finalPrice']);
        $this->assertSame('Limit slevy vyčerpán', $result['reason']);
    }

    public function testGetRemainingQuota(): void
    {
        $product = $this->createProduct('100.00');
        $user = $this->createUserWithRoles(['organizator']);

        $discount = new ProductDiscount();
        $discount->setProduct($product);
        $discount->setRole('organizator');
        $discount->setDiscountPercent('25.00');
        $discount->setMaxQuantity(3);

        $this->discountRepository
            ->expects($this->once())
            ->method('findBestDiscountForProduct')
            ->with($product, ['organizator'])
            ->willReturn($discount);

        $this->orderItemRepository
            ->expects($this->once())
            ->method('countCustomerPurchases')
            ->with($user, $product, 2025)
            ->willReturn(1);

        $remaining = $this->calculator->getRemainingQuota($product, $user, 2025);

        $this->assertSame(2, $remaining);
    }

    public function testGetRemainingQuotaWithNoRoles(): void
    {
        $product = $this->createProduct('100.00');
        $user = new User();

        $remaining = $this->calculator->getRemainingQuota($product, $user, 2025);

        $this->assertNull($remaining);
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
     * Create user with specified role codes
     *
     * @param string[] $roleCodes
     */
    private function createUserWithRoles(array $roleCodes): User
    {
        $user = new User();

        foreach ($roleCodes as $roleCode) {
            $role = new Role();
            $role->setKodRole($roleCode);

            $userRole = new UserRole();
            $userRole->setUser($user);
            $userRole->setRole($role);

            // Use reflection to add UserRole to collection
            $reflection = new \ReflectionClass($user);
            $property = $reflection->getProperty('userRoles');
            $property->setAccessible(true);
            $collection = $property->getValue($user);
            $collection->add($userRole);
        }

        return $user;
    }
}
