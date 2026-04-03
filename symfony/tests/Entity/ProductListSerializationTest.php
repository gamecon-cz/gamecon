<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Dto\Cart\MealProductOutputDto;
use App\Entity\Product;
use App\Entity\ProductTag;
use App\Entity\ProductVariant;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Verifies serialization groups for product listing and
 * the MealProductOutputDto structure for the meal matrix.
 */
class ProductListSerializationTest extends TestCase
{
    /**
     * Core product fields must be in product:list for the public product listing API.
     *
     * @dataProvider productFieldsRequiredInList
     */
    public function testProductFieldHasListGroup(string $fieldName): void
    {
        $reflection = new \ReflectionProperty(Product::class, $fieldName);
        $attributes = $reflection->getAttributes(Groups::class);

        $this->assertNotEmpty($attributes, "Product::\${$fieldName} must have #[Groups] attribute");

        $groups = $attributes[0]->newInstance()->getGroups();
        $this->assertContains('product:list', $groups, "Product::\${$fieldName} must include 'product:list' group");
    }

    public static function productFieldsRequiredInList(): array
    {
        return [
            'id' => ['id'],
            'name' => ['name'],
            'code' => ['code'],
            'currentPrice' => ['currentPrice'],
            'state' => ['state'],
        ];
    }

    /**
     * Tags and variants must NOT be in product:list — they are served
     * via dedicated DTOs (e.g. MealProductOutputDto) to avoid
     * serialization group complexity.
     *
     * @dataProvider productFieldsNotInList
     */
    public function testProductFieldDoesNotHaveListGroup(string $fieldName): void
    {
        $reflection = new \ReflectionProperty(Product::class, $fieldName);
        $attributes = $reflection->getAttributes(Groups::class);

        if (empty($attributes)) {
            $this->assertTrue(true); // no groups at all = not in list
            return;
        }

        $groups = $attributes[0]->newInstance()->getGroups();
        $this->assertNotContains('product:list', $groups, "Product::\${$fieldName} must NOT be in 'product:list' — use dedicated DTOs instead");
    }

    public static function productFieldsNotInList(): array
    {
        return [
            'accommodationDay' => ['accommodationDay'],
            'tags' => ['tags'],
            'variants' => ['variants'],
        ];
    }

    /**
     * @dataProvider tagFieldsRequiredInProductRead
     */
    public function testTagFieldHasProductReadGroup(string $fieldName): void
    {
        $reflection = new \ReflectionProperty(ProductTag::class, $fieldName);
        $attributes = $reflection->getAttributes(Groups::class);

        $this->assertNotEmpty($attributes, "ProductTag::\${$fieldName} must have #[Groups] attribute");

        $groups = $attributes[0]->newInstance()->getGroups();
        $this->assertContains('product:read', $groups);
    }

    public static function tagFieldsRequiredInProductRead(): array
    {
        return [
            'code' => ['code'],
            'name' => ['name'],
        ];
    }

    public function testMealProductOutputDtoHasAllRequiredFields(): void
    {
        $product = new Product();
        $product->setName('Oběd — pátek');
        $product->setCode('obed-patek');
        $product->setCurrentPrice('120.00');
        $product->setState(1);
        $product->setDescription('');
        $product->setAccommodationDay(2);

        $variant = new ProductVariant();
        $variant->setProduct($product);
        $variant->setName('Default');
        $variant->setCode('obed-patek-v');
        $variant->setRemainingQuantity(50);
        $variant->setAccommodationDay(2);

        $ref = new \ReflectionProperty(ProductVariant::class, 'id');
        $ref->setValue($variant, 99);

        $dto = MealProductOutputDto::fromProductAndVariant($product, $variant);

        $this->assertSame('Oběd — pátek', $dto->name);
        $this->assertSame(2, $dto->day);
        $this->assertSame('120.00', $dto->price);
        $this->assertSame(99, $dto->variantId);
        $this->assertSame(50, $dto->remainingQuantity);
    }

    public function testMealProductOutputDtoUsesVariantAccommodationDay(): void
    {
        $product = new Product();
        $product->setName('Test');
        $product->setCode('TEST');
        $product->setCurrentPrice('100.00');
        $product->setState(1);
        $product->setDescription('');
        $product->setAccommodationDay(1);

        $variant = new ProductVariant();
        $variant->setProduct($product);
        $variant->setName('V');
        $variant->setCode('TEST-V');
        $variant->setAccommodationDay(3); // variant overrides product

        $ref = new \ReflectionProperty(ProductVariant::class, 'id');
        $ref->setValue($variant, 1);

        $dto = MealProductOutputDto::fromProductAndVariant($product, $variant);

        $this->assertSame(3, $dto->day, 'Should use variant accommodationDay over product');
    }
}
