<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Enum\ProductStateEnum;
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
        $this->product->setState(ProductStateEnum::PUBLIC);
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

    public function testNewVariantIsOfferedLikeItsProduct(): void
    {
        $this->product->setState(ProductStateEnum::SUSPENDED);

        $variant = $this->createVariant('M', 'TRICKO-MODRE-M');
        $variant->startOfferedAsProduct();

        $this->assertSame(ProductStateEnum::SUSPENDED, $variant->getState());
    }

    /**
     * A night goes on sale while its room type, which nobody buys, stays suspended.
     */
    public function testVariantKeepsItsOwnState(): void
    {
        $this->product->setState(ProductStateEnum::SUSPENDED);
        $variant = new ProductVariant();
        $variant->setState(ProductStateEnum::PUBLIC);

        $variant->setProduct($this->product);
        $variant->startOfferedAsProduct();

        $this->assertSame(ProductStateEnum::PUBLIC, $variant->getState());
    }

    public function testDefaultVariantIsNamedAfterItsProduct(): void
    {
        $variant = new ProductVariant();
        $variant->setProduct($this->product);
        $variant->setCode('TRICKO-MODRE');

        $this->assertNull($variant->getName());
        $this->assertSame('Tričko modré', $variant->getFullName());
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
