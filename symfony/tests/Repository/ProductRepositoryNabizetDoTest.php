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
    private function vlozRadekNoci(string $kod, ?string $nabizetDo, int $stav = ProductStateEnum::PUBLIC->value): void
    {
        $this->connection()->executeStatement(
            'INSERT INTO shop_predmety
                (nazev, kod_predmetu, cena_aktualni, stav, nabizet_do, kusu_vyrobeno, popis)
             VALUES (:nazev, :kod, 0, :stav, :nabizetDo, 10, :popis)',
            [
                'nazev'     => 'Test noc ' . $kod,
                'kod'       => $kod,
                'stav'      => $stav,
                'nabizetDo' => $nabizetDo,
                'popis'     => '',
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

        $nalezene = $this->repository()->producedQuantityByVariantCode([$kod]);

        self::assertTrue(
            $nalezene[$kod]['nabizeno'],
            'Ubytování se neřídí sloupcem nabizet_do, takže prošlé datum nesmí noc zamknout',
        );
    }

    public function testPozastavenaNocNeniNabizena(): void
    {
        $kod = 'test-noc-pozastavena-' . uniqid();
        $this->vlozRadekNoci($kod, null, ProductStateEnum::SUSPENDED->value);

        $nalezene = $this->repository()->producedQuantityByVariantCode([$kod]);

        self::assertFalse($nalezene[$kod]['nabizeno']);
    }

    public function testVerejnaNocJeNabizena(): void
    {
        $kod = 'test-noc-verejna-' . uniqid();
        $this->vlozRadekNoci($kod, null);

        $nalezene = $this->repository()->producedQuantityByVariantCode([$kod]);

        self::assertTrue($nalezene[$kod]['nabizeno']);
        self::assertSame(10, $nalezene[$kod]['vyrobeno']);
    }
}
