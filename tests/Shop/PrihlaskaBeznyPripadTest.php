<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

use Gamecon\Role\Role;

/**
 * Co po přechodu na košík zbylo z přihlášky jako formuláře: samotné přihlášení na GC.
 * Objednávky formulář nezapisuje — merch pokrývá App\Tests\Service\CartServiceStockTest,
 * ubytování App\Tests\Service\AccommodationWriterTest.
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
}
