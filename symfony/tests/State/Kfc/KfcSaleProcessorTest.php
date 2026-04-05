<?php

declare(strict_types=1);

namespace App\Tests\State\Kfc;

use ApiPlatform\Metadata\Post;
use App\Dto\Kfc\KfcSaleInputDto;
use App\Dto\Kfc\KfcSaleItemInputDto;
use App\Dto\Kfc\KfcSaleOutputDto;
use App\Service\CurrentYearProviderInterface;
use App\State\Kfc\KfcSaleProcessor;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class KfcSaleProcessorTest extends TestCase
{
    private MockObject $connection;

    private MockObject $yearProvider;

    private KfcSaleProcessor $processor;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->yearProvider = $this->createMock(CurrentYearProviderInterface::class);
        $this->yearProvider->method('getCurrentYear')->willReturn(2026);

        $this->processor = new KfcSaleProcessor(
            $this->connection,
            $this->yearProvider,
        );
    }

    public function testSuccessfulSale(): void
    {
        $this->connection->method('fetchAssociative')
            ->willReturn(['cena_aktualni' => '250.00', 'kusu_vyrobeno' => '100', 'nazev' => 'Tričko']);

        $this->connection->method('fetchOne')
            ->willReturn('10'); // 10 already sold

        // Expect 3 INSERT statements (quantity = 3)
        $this->connection->expects($this->exactly(3))
            ->method('executeStatement');

        $input = new KfcSaleInputDto();
        $item = new KfcSaleItemInputDto();
        $item->productId = 42;
        $item->quantity = 3;
        $input->items = [$item];

        $result = $this->processor->process($input, new Post());

        $this->assertInstanceOf(KfcSaleOutputDto::class, $result);
        $this->assertSame(3, $result->soldItems);
        $this->assertSame('750', $result->totalPrice);
    }

    public function testSaleThrowsWhenProductNotFound(): void
    {
        $this->connection->method('fetchAssociative')
            ->willReturn(false);

        $input = new KfcSaleInputDto();
        $item = new KfcSaleItemInputDto();
        $item->productId = 999;
        $item->quantity = 1;
        $input->items = [$item];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('999');

        $this->processor->process($input, new Post());
    }

    public function testSaleThrowsWhenInsufficientStock(): void
    {
        $this->connection->method('fetchAssociative')
            ->willReturn(['cena_aktualni' => '100.00', 'kusu_vyrobeno' => '10', 'nazev' => 'Kostka']);

        $this->connection->method('fetchOne')
            ->willReturn('9'); // 9 sold, only 1 remaining

        $input = new KfcSaleInputDto();
        $item = new KfcSaleItemInputDto();
        $item->productId = 1;
        $item->quantity = 5; // want 5, only 1 available
        $input->items = [$item];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Nedostatek');

        $this->processor->process($input, new Post());
    }

    public function testSaleWithUnlimitedStock(): void
    {
        $this->connection->method('fetchAssociative')
            ->willReturn(['cena_aktualni' => '50.00', 'kusu_vyrobeno' => null, 'nazev' => 'Vstupné']);

        // fetchOne should NOT be called (no stock check for unlimited)
        $this->connection->expects($this->never())
            ->method('fetchOne');

        $this->connection->expects($this->exactly(2))
            ->method('executeStatement');

        $input = new KfcSaleInputDto();
        $item = new KfcSaleItemInputDto();
        $item->productId = 5;
        $item->quantity = 2;
        $input->items = [$item];

        $result = $this->processor->process($input, new Post());

        $this->assertSame(2, $result->soldItems);
        $this->assertSame('100', $result->totalPrice);
    }

    public function testMultipleItemSale(): void
    {
        $callCount = 0;
        $this->connection->method('fetchAssociative')
            ->willReturnCallback(function () use (&$callCount) {
                $callCount++;
                return match ($callCount) {
                    1 => ['cena_aktualni' => '250.00', 'kusu_vyrobeno' => null, 'nazev' => 'Tričko'],
                    2 => ['cena_aktualni' => '100.00', 'kusu_vyrobeno' => null, 'nazev' => 'Kostka'],
                    default => false,
                };
            });

        // 2 items: 1x Tričko + 3x Kostka = 4 INSERTs
        $this->connection->expects($this->exactly(4))
            ->method('executeStatement');

        $input = new KfcSaleInputDto();

        $item1 = new KfcSaleItemInputDto();
        $item1->productId = 1;
        $item1->quantity = 1;

        $item2 = new KfcSaleItemInputDto();
        $item2->productId = 2;
        $item2->quantity = 3;

        $input->items = [$item1, $item2];

        $result = $this->processor->process($input, new Post());

        $this->assertSame(4, $result->soldItems);
        $this->assertSame('550', $result->totalPrice);
    }
}
