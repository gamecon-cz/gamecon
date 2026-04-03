<?php

declare(strict_types=1);

namespace App\Tests\Dto;

use App\Dto\Cart\CartItemOutputDto;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\ProductBundle;
use App\Entity\ProductVariant;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class CartItemOutputDtoTest extends TestCase
{
    public function testFromOrderItemIncludesVariantId(): void
    {
        $product = new Product();
        $product->setName('Oběd pátek');
        $product->setCode('obed-patek');
        $product->setCurrentPrice('150.00');
        $product->setState(1);
        $product->setDescription('');

        $variant = new ProductVariant();
        $variant->setProduct($product);
        $variant->setName('Standard');
        $variant->setCode('obed-patek-std');
        $product->addVariant($variant);

        $ref = new \ReflectionProperty(ProductVariant::class, 'id');
        $ref->setValue($variant, 42);

        $item = new OrderItem();
        $item->setProduct($product);
        $item->setVariant($variant);
        $item->setPurchasePrice('150.00');
        $item->setYear(2026);
        $item->setCustomer($this->createMock(User::class));
        $item->snapshotProduct($product, $variant);

        $dto = CartItemOutputDto::fromOrderItem($item);

        $this->assertSame(42, $dto->variantId);
        $this->assertSame('Standard', $dto->variantName);
        $this->assertSame('obed-patek-std', $dto->variantCode);
        $this->assertSame('150.00', $dto->purchasePrice);
    }

    public function testFromOrderItemWithNullVariant(): void
    {
        $product = new Product();
        $product->setName('Test');
        $product->setCode('TEST');
        $product->setCurrentPrice('100.00');
        $product->setState(1);
        $product->setDescription('');

        $item = new OrderItem();
        $item->setProduct($product);
        $item->setPurchasePrice('100.00');
        $item->setYear(2026);
        $item->setCustomer($this->createMock(User::class));
        $item->snapshotProduct($product);

        $dto = CartItemOutputDto::fromOrderItem($item);

        $this->assertNull($dto->variantId);
        $this->assertNull($dto->variantName);
        $this->assertNull($dto->variantCode);
    }

    public function testFromOrderItemWithBundle(): void
    {
        $bundle = new ProductBundle();
        $bundle->setName('Weekend');
        $bundle->setForced(true);
        $bundle->setApplicableToRoles([]);

        $ref = new \ReflectionProperty(ProductBundle::class, 'id');
        $ref->setValue($bundle, 7);

        $product = new Product();
        $product->setName('Test');
        $product->setCode('TEST');
        $product->setCurrentPrice('100.00');
        $product->setState(1);
        $product->setDescription('');

        $item = new OrderItem();
        $item->setProduct($product);
        $item->setBundle($bundle);
        $item->setPurchasePrice('100.00');
        $item->setYear(2026);
        $item->setCustomer($this->createMock(User::class));
        $item->snapshotProduct($product);

        $dto = CartItemOutputDto::fromOrderItem($item);

        $this->assertSame(7, $dto->bundleId);
    }
}
