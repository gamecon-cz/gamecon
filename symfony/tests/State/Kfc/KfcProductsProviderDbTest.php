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
 *
 * Endpoint obsluhuje mřížku pultu i editor mřížek, takže vrací celý katalog: buňka
 * odkazující na produkt, který by se odfiltroval, by zůstala bez názvu a ceny.
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

    public function testArchivniPredmetSeVratiOznaceny(): void
    {
        // Na starších mřížkách je nakonfigurovaný, takže se posílá — jen označený, ať si
        // ho editor i mřížka umí odlišit od letošní nabídky.
        $nazev = 'Placka loni ' . uniqid();
        $this->vytvorPredmet($nazev, zbyvaNaVariante: 3, kusuVyrobeno: 3, archivedAt: '2025-12-31 23:59:59');

        $polozka = $this->najdi($nazev);

        self::assertNotNull($polozka);
        self::assertTrue($polozka->archived);
    }

    public function testStazenyPredmetSeVratiTaky(): void
    {
        // Na živých mřížkách velikostí je 34 buněk odkazujících na stažené produkty —
        // bez nich by zůstaly bez názvu i ceny. Co je prodejné, rozhoduje prodej.
        $nazev = 'Stažené ' . uniqid();
        $this->vytvorPredmet($nazev, zbyvaNaVariante: 3, kusuVyrobeno: 3, stav: ProductStateEnum::RETIRED);

        self::assertNotNull($this->najdi($nazev));
    }

    public function testPredmetSVicVariantamiNeseSveVarianty(): void
    {
        $nazev = 'Tričko ' . uniqid();
        $this->vytvorPredmet($nazev, zbyvaNaVariante: 5, kusuVyrobeno: 5, variant: 3);

        $polozka = $this->najdi($nazev);

        self::assertNotNull($polozka);
        self::assertCount(3, $polozka->variants);
        // Zásoba produktu nedává smysl, když ji drží každá varianta zvlášť.
        self::assertNull($polozka->remaining);
        self::assertSame(5, $polozka->variants[0]->remaining);
    }

    public function testJednovariantniPredmetNeseSvouVariantu(): void
    {
        $nazev = 'Kostka jedna ' . uniqid();
        $this->vytvorPredmet($nazev, zbyvaNaVariante: 9, kusuVyrobeno: 9);

        $polozka = $this->najdi($nazev);

        self::assertNotNull($polozka);
        self::assertCount(1, $polozka->variants);
        self::assertSame(9, $polozka->remaining);
        self::assertFalse($polozka->archived);
    }
}
