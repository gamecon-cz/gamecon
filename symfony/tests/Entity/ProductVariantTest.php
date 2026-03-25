<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Enum\RoleMeaning;
use PHPUnit\Framework\TestCase;

class ProductVariantTest extends TestCase
{
    private Product $product;

    protected function setUp(): void
    {
        $this->product = new Product();
        $this->product->setName('Tričko modré');
        $this->product->setCode('TRICKO-MODRE');
        $this->product->setCurrentPrice('250.00');
        $this->product->setState(1);
        $this->product->setReservedForOrganizers(5);
    }

    public function testGetEffectivePriceInheritsFromProduct(): void
    {
        $variant = $this->createVariant('M', 'TRICKO-MODRE-M');

        $this->assertNull($variant->getPrice());
        $this->assertSame('250.00', $variant->getEffectivePrice());
    }

    public function testGetEffectivePriceUsesOwnPrice(): void
    {
        $variant = $this->createVariant('M', 'TRICKO-MODRE-M');
        $variant->setPrice('199.00');

        $this->assertSame('199.00', $variant->getEffectivePrice());
    }

    public function testGetEffectiveReservedInheritsFromProduct(): void
    {
        $variant = $this->createVariant('M', 'TRICKO-MODRE-M');

        $this->assertNull($variant->getReservedForOrganizers());
        $this->assertSame(5, $variant->getEffectiveReservedForOrganizers());
    }

    public function testGetEffectiveReservedUsesOwnValue(): void
    {
        $variant = $this->createVariant('M', 'TRICKO-MODRE-M');
        $variant->setReservedForOrganizers(2);

        $this->assertSame(2, $variant->getEffectiveReservedForOrganizers());
    }

    public function testGetAvailableQuantityUnlimited(): void
    {
        $variant = $this->createVariant('M', 'TRICKO-MODRE-M');

        $this->assertNull($variant->getAvailableQuantity([]));
        $this->assertFalse($variant->hasLimitedCapacity());
    }

    public function testGetAvailableQuantityWithReservation(): void
    {
        $variant = $this->createVariant('M', 'TRICKO-MODRE-M');
        $variant->setRemainingQuantity(20);
        // reservedForOrganizers = null → inherits 5 from product

        $this->assertSame(15, $variant->getAvailableQuantity([]));
        $this->assertSame(20, $variant->getAvailableQuantity([RoleMeaning::ORGANIZATOR_ZDARMA]));
        $this->assertSame(20, $variant->getAvailableQuantity([RoleMeaning::VYPRAVEC]));
    }

    public function testGetAvailableQuantityWithOwnReservation(): void
    {
        $variant = $this->createVariant('M', 'TRICKO-MODRE-M');
        $variant->setRemainingQuantity(10);
        $variant->setReservedForOrganizers(3); // overrides product's 5

        $this->assertSame(7, $variant->getAvailableQuantity([]));
        $this->assertSame(10, $variant->getAvailableQuantity([RoleMeaning::ORGANIZATOR_ZDARMA]));
    }

    public function testGetFullName(): void
    {
        $variant = $this->createVariant('M', 'TRICKO-MODRE-M');

        $this->assertSame('Tričko modré — M', $variant->getFullName());
    }

    public function testProductVariantCollection(): void
    {
        $variantS = $this->createVariant('S', 'TRICKO-MODRE-S');
        $variantM = $this->createVariant('M', 'TRICKO-MODRE-M');

        $this->product->addVariant($variantS);
        $this->product->addVariant($variantM);

        $this->assertTrue($this->product->hasVariants());
        $this->assertCount(2, $this->product->getVariants());

        $this->product->removeVariant($variantS);
        $this->assertCount(1, $this->product->getVariants());
    }

    public function testProductWithoutVariants(): void
    {
        $this->assertFalse($this->product->hasVariants());
        $this->assertCount(0, $this->product->getVariants());
    }

    public function testAccommodationDay(): void
    {
        $variant = $this->createVariant('Pátek', 'UBYT-PATEK');
        $variant->setAccommodationDay(2);

        $this->assertSame(2, $variant->getAccommodationDay());
    }

    public function testMultipleOrgRolesCountAsOrganizer(): void
    {
        $variant = $this->createVariant('M', 'TRICKO-MODRE-M');
        $variant->setRemainingQuantity(5);
        $variant->setReservedForOrganizers(5);

        // Multiple roles, one of which is organizer → full access
        $roles = [RoleMeaning::PRIHLASEN, RoleMeaning::BRIGADNIK];
        $this->assertSame(5, $variant->getAvailableQuantity($roles));

        // No org role → 0
        $this->assertSame(0, $variant->getAvailableQuantity([RoleMeaning::PRIHLASEN]));
    }

    private function createVariant(string $name, string $code): ProductVariant
    {
        $variant = new ProductVariant();
        $variant->setProduct($this->product);
        $variant->setName($name);
        $variant->setCode($code);

        return $variant;
    }
}
