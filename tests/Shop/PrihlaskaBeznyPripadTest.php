<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

/**
 * Běžný případ přihlášky: účastník se přihlásí na GC a objedná si ubytování,
 * tričko a jídlo. Ověřuje, že se objednávka uloží celá.
 *
 * Běžný merch formulář neposílá — kupuje se přes API košíku, viz
 * App\Tests\Service\CartServiceStockTest.
 */
class PrihlaskaBeznyPripadTest extends AbstractTestPrihlaska
{
    /**
     * @test
     */
    public function odhlaseniZUbytovaniSmazeVsechnyNoci(): void
    {
        $uzivatel = $this->vytvorBeznehoUzivatele();
        $idsNoci = $this->vytvorUbytovaniNaDvouNocich(kusuVyrobeno: 10, cena: 300.0);

        $this->odesliPrihlasku($uzivatel, [
            'shopUbytovaniDny' => $idsNoci,
        ]);
        self::assertSame(2, $this->pocetVsechNakupu($uzivatel));

        $this->odesliPrihlasku($uzivatel, [
            'shopUbytovaniDny'    => [],
            'shopUbytovaniNechci' => '1',
        ]);

        self::assertSame(0, $this->pocetVsechNakupu($uzivatel), 'Zrušení ubytování musí smazat všechny objednané noci');
        self::assertTrue(
            \Uzivatel::zIdUrcite($uzivatel->id())->nechceUbytovani(),
            'Zaškrtnutí „nechci ubytování“ se musí uložit',
        );
    }
}
