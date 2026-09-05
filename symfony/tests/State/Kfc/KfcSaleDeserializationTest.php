<?php

declare(strict_types=1);

namespace App\Tests\State\Kfc;

use App\Dto\Kfc\KfcSaleInputDto;
use App\Dto\Kfc\KfcSaleItemInputDto;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * Tests that Symfony serializer correctly deserializes nested DTOs
 * from JSON input (as API Platform would).
 */
class KfcSaleDeserializationTest extends TestCase
{
    private Serializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new Serializer(
            [new ObjectNormalizer(propertyTypeExtractor: new PhpDocExtractor()), new ArrayDenormalizer()],
            [new JsonEncoder()],
        );
    }

    public function testDeserializeSaleInputWithNestedItems(): void
    {
        $json = '{"items": [{"productId": 42, "quantity": 3}, {"productId": 7, "quantity": 1}]}';

        $dto = $this->serializer->deserialize($json, KfcSaleInputDto::class, 'json');

        $this->assertInstanceOf(KfcSaleInputDto::class, $dto);
        $this->assertCount(2, $dto->items);

        // Items must be KfcSaleItemInputDto instances, not plain arrays
        $this->assertInstanceOf(
            KfcSaleItemInputDto::class,
            $dto->items[0],
            'Nested items must be deserialized as KfcSaleItemInputDto, not plain arrays. '
            . 'Actual type: ' . get_debug_type($dto->items[0]),
        );
        $this->assertSame(42, $dto->items[0]->productId);
        $this->assertSame(3, $dto->items[0]->quantity);

        $this->assertInstanceOf(KfcSaleItemInputDto::class, $dto->items[1]);
        $this->assertSame(7, $dto->items[1]->productId);
        $this->assertSame(1, $dto->items[1]->quantity);
    }
}
