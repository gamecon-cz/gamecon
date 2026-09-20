<?php

declare(strict_types=1);

namespace App\Tests\State\Kfc;

use ApiPlatform\Metadata\GetCollection;
use App\Dto\Kfc\KfcProductOutputDto;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Enum\ProductStateEnum;
use App\State\Kfc\KfcProductsProvider;
use App\Tests\AbstractDatabaseKernelTestCase;

/**
 * Číslo na pultu musí souhlasit s tím, kolik prodej doopravdy pustí. Vynucuje ho
 * `CapacityManager::purchase()` přes `product_variant.remaining_quantity`, takže se čte
 * odtamtud — dopočítávat ho z `kusu_vyrobeno` minus nákupy dávalo jiné číslo.
 */
class KfcProductsProviderDbTest extends AbstractDatabaseKernelTestCase
{
    private function provider(): KfcProductsProvider
    {
        return static::getContainer()->get(KfcProductsProvider::class);
    }

    private function vytvorPredmet(
        string           $nazev,
        ?int             $zbyvaNaVariante,
        int              $kusuVyrobeno,
        ProductStateEnum $stav = ProductStateEnum::PUBLIC,
        ?string          $archivedAt = null,
        int              $variant = 1,
    ): Product {
        $kod = 'kfc-' . uniqid();

        $produkt = new Product();
        $produkt->setName($nazev);
        $produkt->setCode($kod);
        $produkt->setCurrentPrice('100.00');
        $produkt->setDescription('');
        $produkt->setState($stav);
        $produkt->setProducedQuantity($kusuVyrobeno);
        if ($archivedAt !== null) {
            $produkt->setArchivedAt(new \DateTimeImmutable($archivedAt));
        }
        $this->entityManager()->persist($produkt);
        $this->entityManager()->flush();

        for ($poradi = 0; $poradi < $variant; $poradi++) {
            $varianta = new ProductVariant();
            $varianta->setProduct($produkt);
            $varianta->setName('kus' . $poradi);
            $varianta->setCode($kod . '-' . $poradi);
            $varianta->setPosition($poradi);
            $varianta->setRemainingQuantity($zbyvaNaVariante);
            $produkt->addVariant($varianta);
            $this->entityManager()->persist($varianta);
        }
        $this->entityManager()->flush();

        return $produkt;
    }

    /**
     * @return KfcProductOutputDto[]
     */
    private function nabidka(): array
    {
        return $this->provider()->provide(new GetCollection());
    }

    private function najdi(string $nazev): ?KfcProductOutputDto
    {
        foreach ($this->nabidka() as $polozka) {
            if ($polozka->name === $nazev) {
                return $polozka;
            }
        }

        return null;
    }

    public function testZasobaOdpovidaVariante(): void
    {
        // `kusu_vyrobeno` schválně jiné: kdyby se počítalo z něj, vyjde 500, ne 7.
        $nazev = 'Kostka ' . uniqid();
        $this->vytvorPredmet($nazev, zbyvaNaVariante: 7, kusuVyrobeno: 500);

        $polozka = $this->najdi($nazev);

        self::assertNotNull($polozka);
        self::assertSame(7, $polozka->remaining);
    }

    public function testNeomezenaZasobaZustaneNeomezena(): void
    {
        $nazev = 'Vstupné ' . uniqid();
        $this->vytvorPredmet($nazev, zbyvaNaVariante: null, kusuVyrobeno: 0);

        $polozka = $this->najdi($nazev);

        self::assertNotNull($polozka);
        self::assertNull($polozka->remaining);
    }

    public function testArchivniPredmetSeNenabizi(): void
    {
        $nazev = 'Placka loni ' . uniqid();
        $this->vytvorPredmet($nazev, zbyvaNaVariante: 3, kusuVyrobeno: 3, archivedAt: '2025-12-31 23:59:59');

        self::assertNull($this->najdi($nazev));
    }

    public function testStazenyPredmetSeNenabizi(): void
    {
        $nazev = 'Stažené ' . uniqid();
        $this->vytvorPredmet($nazev, zbyvaNaVariante: 3, kusuVyrobeno: 3, stav: ProductStateEnum::RETIRED);

        self::assertNull($this->najdi($nazev));
    }

    /**
     * Pult umí prodat jen jednoznačný předmět — u víc variant `KfcSaleProcessor` prodej
     * odmítne, takže ho nemá smysl nabízet.
     */
    public function testPredmetSVicVariantamiSeNenabizi(): void
    {
        $nazev = 'Tričko ' . uniqid();
        $this->vytvorPredmet($nazev, zbyvaNaVariante: 5, kusuVyrobeno: 5, variant: 3);

        self::assertNull($this->najdi($nazev));
    }
}
