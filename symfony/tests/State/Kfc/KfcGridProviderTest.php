<?php

declare(strict_types=1);

namespace App\Tests\State\Kfc;

use ApiPlatform\Metadata\GetCollection;
use App\Dto\Kfc\KfcGridOutputDto;
use App\State\Kfc\KfcGridProvider;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class KfcGridProviderTest extends TestCase
{
    private MockObject $connection;

    private KfcGridProvider $provider;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->provider = new KfcGridProvider($this->connection);
    }

    public function testReturnsGridsWithCells(): void
    {
        $this->connection->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls(
                // grids
                [
                    ['id' => '1', 'text' => 'Úvod'],
                    ['id' => '2', 'text' => 'Trička'],
                ],
                // cells
                [
                    ['id' => '10', 'typ' => '0', 'text' => null, 'barva' => '#f4bb57', 'barva_text' => '#000', 'cil_id' => '42', 'mrizka_id' => '1'],
                    ['id' => '11', 'typ' => '1', 'text' => 'Trička', 'barva' => '#ff0000', 'barva_text' => null, 'cil_id' => '2', 'mrizka_id' => '1'],
                    ['id' => '12', 'typ' => '2', 'text' => 'Zpět', 'barva' => null, 'barva_text' => null, 'cil_id' => null, 'mrizka_id' => '2'],
                ],
            );

        $result = $this->provider->provide(new GetCollection());

        $this->assertCount(2, $result);

        // Grid 1 has 2 cells
        $this->assertInstanceOf(KfcGridOutputDto::class, $result[0]);
        $this->assertSame(1, $result[0]->id);
        $this->assertSame('Úvod', $result[0]->text);
        $this->assertCount(2, $result[0]->bunky);

        $productCell = $result[0]->bunky[0];
        $this->assertSame(0, $productCell->typ);
        $this->assertSame('#f4bb57', $productCell->barva);
        $this->assertSame(42, $productCell->cilId);

        $pageCell = $result[0]->bunky[1];
        $this->assertSame(1, $pageCell->typ);
        $this->assertSame('Trička', $pageCell->text);
        $this->assertSame(2, $pageCell->cilId);

        // Grid 2 has 1 cell
        $this->assertSame(2, $result[1]->id);
        $this->assertCount(1, $result[1]->bunky);
        $this->assertSame(2, $result[1]->bunky[0]->typ); // back
    }

    public function testReturnsEmptyGridsWhenNoCells(): void
    {
        $this->connection->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls(
                [['id' => '1', 'text' => 'Empty grid']],
                [], // no cells
            );

        $result = $this->provider->provide(new GetCollection());

        $this->assertCount(1, $result);
        $this->assertSame([], $result[0]->bunky);
    }
}
