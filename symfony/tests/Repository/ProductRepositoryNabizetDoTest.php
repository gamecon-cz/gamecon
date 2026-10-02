<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Enum\ProductStateEnum;
use App\Repository\ProductRepository;
use App\Tests\AbstractDatabaseKernelTestCase;

/**
 * Nocleh se na rozdíl od ostatního zboží neřídí sloupcem `nabizet_do` — legacy shop ho
 * u ubytování ignoruje a zamyká noc až stavem POZASTAVENY. Kdyby se tenhle sloupec začal
 * ctít i tady, zmizely by noci, které se přes přihlášku pořád prodávají.
 */
class ProductRepositoryNabizetDoTest extends AbstractDatabaseKernelTestCase
{
    /**
     * A night is a variant of its room type and has no catalog row of its own; the room type
     * carries `nabizet_do`.
     */
    private function vlozRadekNoci(
        string $kod,
        ?string $nabizetDo,
        int $stav = ProductStateEnum::PUBLIC->value,
    ): void {
        $this->connection()->executeStatement(
            'INSERT INTO shop_predmety
                (nazev, kod_predmetu, cena_aktualni, stav, nabizet_do, popis)
             VALUES (:nazev, :kod, 0, :stav, :nabizetDo, :popis)',
            [
                'nazev'     => 'Test typ pokoje ' . $kod,
                'kod'       => $kod . '-typ',
                'stav'      => ProductStateEnum::SUSPENDED->value,
                'nabizetDo' => $nabizetDo,
                'popis'     => '',
            ],
        );
        $this->connection()->executeStatement(
            'INSERT INTO product_variant (product_id, name, code, capacity, accommodation_day, position, state)
             VALUES (:produkt, :nazev, :kod, 10, 2, 0, :stav)',
            [
                'produkt' => $this->connection()->lastInsertId(),
                'nazev'   => 'pátek',
                'kod'     => $kod,
                'stav'    => $stav,
            ],
        );
    }

    private function repository(): ProductRepository
    {
        return static::getContainer()->get(ProductRepository::class);
    }

    public function testNocSProslymNabizetDoZustavaNabizena(): void
    {
        $kod = 'test-noc-prosla-' . uniqid();
        $this->vlozRadekNoci($kod, '2000-01-01 00:00:00');

        $nalezene = $this->repository()->capacityByVariantCode([$kod]);

        self::assertTrue(
            $nalezene[$kod]['nabizeno'],
            'Ubytování se neřídí sloupcem nabizet_do, takže prošlé datum nesmí noc zamknout',
        );
    }

    public function testPozastavenaNocNeniNabizena(): void
    {
        $kod = 'test-noc-pozastavena-' . uniqid();
        $this->vlozRadekNoci($kod, null, ProductStateEnum::SUSPENDED->value);

        $nalezene = $this->repository()->capacityByVariantCode([$kod]);

        self::assertFalse($nalezene[$kod]['nabizeno']);
    }

    public function testVerejnaNocJeNabizena(): void
    {
        $kod = 'test-noc-verejna-' . uniqid();
        $this->vlozRadekNoci($kod, null);

        $nalezene = $this->repository()->capacityByVariantCode([$kod]);

        self::assertTrue($nalezene[$kod]['nabizeno']);
        self::assertSame(10, $nalezene[$kod]['vyrobeno']);
    }

    /**
     * A room type nobody buys stays suspended while its nights are on sale.
     */
    public function testONabizeniRozhodujeStavNociNeTypuPokoje(): void
    {
        $kod = 'test-noc-typ-pozastaveny-' . uniqid();
        $this->vlozRadekNoci($kod, null);

        $nalezene = $this->repository()->capacityByVariantCode([$kod]);

        self::assertTrue($nalezene[$kod]['nabizeno']);
    }

    public function testNocArchivovanehoTypuPokojeNeniNabizena(): void
    {
        $kod = 'test-noc-archivovana-' . uniqid();
        $this->vlozRadekNoci($kod, null);
        $this->connection()->executeStatement('UPDATE shop_predmety SET archived_at = NOW() WHERE kod_predmetu = :kod', [
            'kod' => $kod . '-typ',
        ]);

        $nalezene = $this->repository()->capacityByVariantCode([$kod]);

        self::assertFalse($nalezene[$kod]['nabizeno']);
    }
}
