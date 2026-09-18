<?php

declare(strict_types=1);

namespace App\Tests\Dto;

use App\Dto\Cart\MealProductOutputDto;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Enum\ProductStateEnum;
use PHPUnit\Framework\TestCase;

class MealProductOutputDtoTest extends TestCase
{
    private function jidlo(?int $zbyva): ProductVariant
    {
        $product = new Product();
        $product->setName('Oběd pátek');
        $product->setCode('obed-patek');
        $product->setCurrentPrice('150.00');
        $product->setState(ProductStateEnum::PUBLIC);
        $product->setDescription('');

        $variant = new ProductVariant();
        $variant->setProduct($product);
        $variant->setName('Standard');
        $variant->setCode('obed-patek-std');
        $variant->setRemainingQuantity($zbyva);
        // DTO vyžaduje id; entita ho jinak dostane až z databáze.
        (new \ReflectionProperty(ProductVariant::class, 'id'))->setValue($variant, 42);

        return $variant;
    }

    /**
     * Záporná zásoba znamená, že se na pultu prodalo víc, než bylo na skladě. Obsluha to
     * má vidět — je to podnět ke kontrole, ne chyba zobrazení, kterou by šlo schovat na
     * nulu. Matici jídel používá účastník i infopult (`JídloMatice` podle `customerId`).
     */
    public function testNegativeStockIsReportedAsIs(): void
    {
        $dto = MealProductOutputDto::fromProductAndVariant(
            $this->jidlo(-2)->getProduct(),
            $this->jidlo(-2),
        );

        self::assertSame(-2, $dto->remainingQuantity);
    }

    /**
     * null = neomezeno; nesmí se z něj stát číslo.
     */
    public function testUnlimitedStaysNull(): void
    {
        $dto = MealProductOutputDto::fromProductAndVariant(
            $this->jidlo(null)->getProduct(),
            $this->jidlo(null),
        );

        self::assertNull($dto->remainingQuantity);
    }
}
