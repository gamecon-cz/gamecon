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
        // Řádek na variantu: produkt s víc variantami přijde vícekrát a provider je složí.
        $this->connection->method('fetchAllAssociative')
            ->willReturn([
                [
                    'id'             => '42',
                    'nazev'          => 'Tričko modré',
                    'cena'           => '250',
                    'archivni'       => '0',
                    'varianta_id'    => '901',
                    'varianta_nazev' => 'S',
                    'varianta_cena'  => '250',
                    'varianta_zbyva' => '15',
                ],
                [
                    'id'             => '42',
                    'nazev'          => 'Tričko modré',
                    'cena'           => '250',
                    'archivni'       => '0',
                    'varianta_id'    => '902',
                    'varianta_nazev' => 'M',
                    'varianta_cena'  => '250',
                    'varianta_zbyva' => '4',
                ],
                [
                    'id'             => '43',
                    'nazev'          => 'Kostka',
                    'cena'           => '50',
                    'archivni'       => '0',
                    'varianta_id'    => '903',
                    'varianta_nazev' => 'kus',
                    'varianta_cena'  => '50',
                    'varianta_zbyva' => null,
                ],
            ]);

        $result = $this->provider->provide(new GetCollection());

        $this->assertCount(2, $result);
        $this->assertInstanceOf(KfcProductOutputDto::class, $result[0]);
        $this->assertSame(42, $result[0]->id);
        $this->assertSame('Tričko modré', $result[0]->name);
        $this->assertSame(250, $result[0]->price);
        $this->assertCount(2, $result[0]->variants);
        // U víc variant drží počty varianty, ne produkt.
        $this->assertNull($result[0]->remaining);
        $this->assertSame('S', $result[0]->variants[0]->name);
        $this->assertSame(15, $result[0]->variants[0]->remaining);

        $this->assertSame(43, $result[1]->id);
        $this->assertCount(1, $result[1]->variants);
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
