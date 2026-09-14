<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

use Gamecon\Role\Role;

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
     * Přihlášení na GC projde `odesliPrihlasku()` při každém odeslání, i když formulář
     * nic neobjednává — role se proto musí zapsat právě jednou, ne při každém odeslání.
     *
     * @test
     */
    public function beznyUzivatelSePrihlasiNaGc(): void
    {
        $uzivatel = $this->vytvorBeznehoUzivatele();
        self::assertFalse($uzivatel->gcPrihlasen(), 'Uživatel nesmí být na GC přihlášen ještě před odesláním přihlášky');

        $this->odesliPrihlasku($uzivatel, []);

        self::assertTrue(
            \Uzivatel::zIdUrcite($uzivatel->id())->gcPrihlasen(),
            'Po odeslání přihlášky musí být uživatel přihlášen na GC',
        );
        self::assertSame(
            1,
            $this->pocetRoliUzivatele($uzivatel, Role::PRIHLASEN_NA_LETOSNI_GC),
            'Přihlášení na GC se musí zapsat právě jednou',
        );
    }

    /**
     * Ubytování se odesílá formulářem i po přechodu merche na košík, takže druhé odeslání
     * téhož obsahu nesmí noci zdvojit.
     *
     * @test
     */
    public function opakovaneOdeslaniPrihlaskySeStejnymObsahemNicNezdvoji(): void
    {
        $uzivatel = $this->vytvorBeznehoUzivatele();
        $idsNoci = $this->vytvorUbytovaniNaDvouNocich(kusuVyrobeno: 10, cena: 300.0);

        $post = [
            'shopUbytovaniDny' => $idsNoci,
        ];

        $this->odesliPrihlasku($uzivatel, $post);
        $this->odesliPrihlasku($uzivatel, $post);

        self::assertSame(
            2,
            $this->pocetVsechNakupu($uzivatel),
            'Druhé odeslání téže přihlášky nesmí zdvojit ubytování',
        );
    }

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
