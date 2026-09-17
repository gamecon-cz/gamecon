<?php

declare(strict_types=1);

namespace App\Tests\Discount;

use App\Discount\DiscountableItem;
use App\Discount\DiscountRule;
use App\Discount\SpentQuota;
use App\Enum\ProductTagCode;
use PHPUnit\Framework\TestCase;

/**
 * Nárok s omezeným počtem je jeden na všechny produkty, které pravidlo pokrývá. Kolik
 * z něj padlo, se pozná jen přehráním nákupů — žebřík počítá jeden produkt a sám o
 * ostatních neví.
 */
class SpentQuotaTest extends TestCase
{
    private function pravidlo(string $code, string $parameters, int $pravo = 1003): DiscountRule
    {
        return DiscountRule::fromRow([
            'code'           => $code,
            'name'           => $code,
            'required_right' => $pravo,
            'parameters'     => $parameters,
        ]);
    }

    private function kostka(string $kod, float $cena): DiscountableItem
    {
        return new DiscountableItem(key: $kod, productCode: $kod, price: $cena, tags: []);
    }

    private function kostkaZdarma(): DiscountRule
    {
        return $this->pravidlo('kostka_zdarma', '{"scope":"code_contains","effect":"free","codeFragment":"kostka","maxQuantity":1}');
    }

    public function testBezNakupuNicSpotrebovanoNeni(): void
    {
        self::assertSame([], SpentQuota::fromPurchases([$this->kostkaZdarma()], []));
    }

    public function testKoupenaKostkaSpotrebujeNarok(): void
    {
        $spentQuota = SpentQuota::fromPurchases(
            [$this->kostkaZdarma()],
            [$this->kostka('fate_kostka', 30.0)],
            rights: [1003],
        );

        self::assertSame(['kostka_zdarma' => 1], $spentQuota);
    }

    /**
     * Kvóta je jedna, i když se nakoupí pět různých kostek — víc než maxQuantity se
     * spotřebovat nedá.
     */
    public function testSpotrebaNepresahneKvotu(): void
    {
        $spentQuota = SpentQuota::fromPurchases([$this->kostkaZdarma()], [
            $this->kostka('fate_kostka', 30.0),
            $this->kostka('draci_kostka', 60.0),
            $this->kostka('duna_kostka', 25.0),
        ], rights: [1003]);

        self::assertSame(['kostka_zdarma' => 1], $spentQuota);
    }

    public function testNarokNaDvaKusySeSpotrebujeDvakrat(): void
    {
        $spentQuota = SpentQuota::fromPurchases(
            [$this->pravidlo('dve_tricka', '{"scope":"tag","effect":"free","tag":"tricko","maxQuantity":2}')],
            [
                new DiscountableItem(key: 'a', productCode: 'tricko_a', price: 400.0, tags: [ProductTagCode::TRICKO]),
                new DiscountableItem(key: 'b', productCode: 'tricko_b', price: 400.0, tags: [ProductTagCode::TRICKO]),
            ],
            rights: [1003],
        );

        self::assertSame(['dve_tricka' => 2], $spentQuota);
    }

    /**
     * Pravidlo bez omezeného počtu se nevyčerpává, takže se nepočítá — jinak by se
     * z kvóty odečítalo něco, co žádnou nemá.
     */
    public function testNeomezenePravidloSeNepocita(): void
    {
        $spentQuota = SpentQuota::fromPurchases(
            [$this->pravidlo('ubytovani_zdarma', '{"scope":"tag","effect":"free","tag":"ubytovani"}')],
            [new DiscountableItem(key: 'a', productCode: 'postel', price: 500.0, tags: [ProductTagCode::UBYTOVANI])],
        );

        self::assertSame([], $spentQuota);
    }

    /**
     * Nárok na konkrétní noc se nespotřebovává — platí na každou koupenou noc toho dne.
     */
    public function testNarokNaNocSeNespotrebovava(): void
    {
        $spentQuota = SpentQuota::fromPurchases(
            [$this->pravidlo('streda_zdarma', '{"scope":"tag_and_day","effect":"free","tag":"ubytovani","day":0,"maxQuantity":1}')],
            [new DiscountableItem(key: 'a', productCode: 'postel', price: 500.0, tags: [ProductTagCode::UBYTOVANI], accommodationDay: 0)],
        );

        self::assertSame([], $spentQuota);
    }

    /**
     * Jedna položka spotřebuje jen jeden nárok, i když na ni sedí dvě pravidla — stejně
     * jako apply(), kde se po první slevě přeruší cyklus.
     */
    public function testJednaPolozkaSpotrebujeJenJedenNarok(): void
    {
        $spentQuota = SpentQuota::fromPurchases([
            $this->kostkaZdarma(),
            $this->pravidlo('kostka_levneji', '{"scope":"code_contains","effect":"percent","codeFragment":"kostka","percent":50,"maxQuantity":1}'),
        ], [$this->kostka('fate_kostka', 30.0)], rights: [1003]);

        self::assertSame(['kostka_zdarma' => 1], $spentQuota);
    }

    /**
     * Nákup, na který žádné pravidlo nesedí, nesmí ubrat z cizí kvóty.
     */
    public function testNesouvisejiciNakupNicNespotrebuje(): void
    {
        $spentQuota = SpentQuota::fromPurchases(
            [$this->kostkaZdarma()],
            [new DiscountableItem(key: 'a', productCode: 'blok', price: 120.0, tags: [])],
        );

        self::assertSame([], $spentQuota);
    }

    /**
     * Pravidlo, na které zákazník nemá právo, nesmí spotřebovat kvótu. Jinak se nárok,
     * který slevu opravdu zaplatil, tváří jako nedotčený a další kus vyjde zdarma znovu.
     */
    public function testPravidloBezPravaKvotuNespotrebuje(): void
    {
        $pravidla = [
            $this->pravidlo('tricko_za_bonus', '{"scope":"tag","effect":"free","tag":"tricko","maxQuantity":1}', 1012),
            $this->pravidlo('jedno_tricko_zdarma', '{"scope":"tag","effect":"free","tag":"tricko","maxQuantity":1}', 1035),
        ];
        $tricko = new DiscountableItem(key: 't', productCode: 'tricko_a', price: 400.0, tags: [ProductTagCode::TRICKO]);

        $spentQuota = SpentQuota::fromPurchases($pravidla, [$tricko], rights: [1035]);

        self::assertSame(['jedno_tricko_zdarma' => 1], $spentQuota);
    }

    /**
     * Kvótu spotřebuje to pravidlo, které slevu doopravdy zaplatilo — i když je to
     * TAG_CHEAPEST, které žebřík sám nabízet neumí. Zapsat místo něj jiné pravidlo by
     * byla lež: nedotčený nárok by se pak rozdal podruhé.
     *
     * Že tenhle zákazník dostane dvě trička zdarma, je správně — drží dva nároky.
     */
    public function testKvotuSpotrebujePravidloKtereSlevuZaplatilo(): void
    {
        $pravidla = [
            $this->pravidlo('tricko_za_bonus', '{"scope":"tag_cheapest","effect":"free","tag":"tricko","maxQuantity":1}', 1012),
            $this->pravidlo('jedno_tricko_zdarma', '{"scope":"tag","effect":"free","tag":"tricko","maxQuantity":1}', 1035),
        ];
        $tricko = new DiscountableItem(key: 't', productCode: 'tricko_a', price: 400.0, tags: [ProductTagCode::TRICKO]);

        $spentQuota = SpentQuota::fromPurchases($pravidla, [$tricko], rights: [1012, 1035]);

        self::assertSame(['tricko_za_bonus' => 1], $spentQuota);
    }

    /**
     * Zákazník s jedním nárokem dostane jedno tričko zdarma, druhé za plnou cenu — tohle
     * je ta chyba, kvůli které spotřeba musí respektovat práva.
     */
    public function testJedenNarokDaJenJednoTrickoZdarma(): void
    {
        $pravidla = [
            $this->pravidlo('tricko_za_bonus', '{"scope":"tag_cheapest","effect":"free","tag":"tricko","maxQuantity":1}', 1012),
            $this->pravidlo('jedno_tricko_zdarma', '{"scope":"tag","effect":"free","tag":"tricko","maxQuantity":1}', 1035),
        ];
        $tricko = new DiscountableItem(key: 't', productCode: 'tricko_a', price: 400.0, tags: [ProductTagCode::TRICKO]);
        $prava = [1035];

        $spentQuota = SpentQuota::fromPurchases($pravidla, [$tricko], rights: $prava);
        self::assertSame(['jedno_tricko_zdarma' => 1], $spentQuota);

        $steps = (new \App\Discount\DiscountCalculation($pravidla, $prava, [], 0.0))
            ->priceSteps($tricko, 1, $spentQuota);
        self::assertSame(400.0, $steps[0]->price, 'Druhé tričko na jeden nárok zdarma není');
    }

    /**
     * Nákup, který žádnou slevu nedostal (nulová cena, nebo nastavená částka 0), nesmí
     * spotřebovat nárok — apply() takové pravidlo na témže místě zahodí.
     */
    public function testNakupBezRealneSlevyKvotuNespotrebuje(): void
    {
        $spentQuota = SpentQuota::fromPurchases(
            [$this->kostkaZdarma()],
            [$this->kostka('fate_kostka', 0.0)],
            rights: [1003],
        );

        self::assertSame([], $spentQuota);
    }
}
