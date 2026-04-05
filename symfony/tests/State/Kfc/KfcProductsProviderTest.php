<?php

declare(strict_types=1);

namespace App\Tests\State\Kfc;

use ApiPlatform\Metadata\GetCollection;
use App\Dto\Kfc\KfcProductOutputDto;
use App\State\Kfc\KfcProductsProvider;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class KfcProductsProviderTest extends TestCase
{
    private MockObject $connection;

    private KfcProductsProvider $provider;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->provider = new KfcProductsProvider($this->connection);
    }

    public function testReturnsProductDtos(): void
    {
        $this->connection->method('fetchAllAssociative')
            ->willReturn([
                ['id' => '42', 'nazev' => 'Tričko modré 2026', 'cena' => '250', 'zbyva' => '15'],
                ['id' => '43', 'nazev' => 'Kostka 2026', 'cena' => '50', 'zbyva' => null],
            ]);

        $result = $this->provider->provide(new GetCollection());

        $this->assertCount(2, $result);
        $this->assertInstanceOf(KfcProductOutputDto::class, $result[0]);
        $this->assertSame(42, $result[0]->id);
        $this->assertSame('Tričko modré 2026', $result[0]->name);
        $this->assertSame(250, $result[0]->price);
        $this->assertSame(15, $result[0]->remaining);

        $this->assertSame(43, $result[1]->id);
        $this->assertNull($result[1]->remaining);
    }

    public function testReturnsEmptyArrayWhenNoProducts(): void
    {
        $this->connection->method('fetchAllAssociative')
            ->willReturn([]);

        $result = $this->provider->provide(new GetCollection());

        $this->assertSame([], $result);
    }
}
