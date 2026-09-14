<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

use Gamecon\Shop\StavPredmetu;

/**
 * Co se vykreslí v nabídce předmětů na přihlášce. Merch se sice kupuje přes košíkové
 * API, ale `Shop::predmetyHtml()` je pořád volaný z `web/moduly/prihlaska/prihlaska.php`,
 * takže jeho výstup někdo vidí a nesmí nabízet, co se nabízet nemá.
 *
 * Pozor: každý test zakládá i běžný předmět. Kdyby byl ten nenabízený jediný, zamkla by
 * se celá sekce předmětů a test by prošel, i kdyby se na jednotlivé předměty vůbec
 * nehledělo.
 */
class PrihlaskaNabidkaPredmetuTest extends AbstractTestPrihlaska
{
    /**
     * @test
     */
    public function nabidkaObsahujeBeznyPredmet(): void
    {
        $uzivatel = $this->vytvorBeznehoUzivatele();
        $idPredmetu = $this->vytvorPredmet('Placka');

        self::assertTrue(
            $this->jeVidetVNabidce($uzivatel, $idPredmetu),
            'Veřejný předmět letošního ročníku se musí nabízet — jinak testy nabídky nic neměří',
        );
    }

    /**
     * @test
     */
    public function nabidkaNeobsahujePozastavenyPredmet(): void
    {
        $uzivatel = $this->vytvorBeznehoUzivatele();
        $idPredmetu = $this->vytvorPredmet('Stažená placka', stav: StavPredmetu::POZASTAVENY);
        $idVerejneho = $this->vytvorPredmet('Veřejná placka');

        self::assertTrue($this->jeVidetVNabidce($uzivatel, $idVerejneho));
        self::assertFalse(
            $this->jeVidetVNabidce($uzivatel, $idPredmetu),
            'Pozastavený předmět se nesmí nabízet k prodeji',
        );
    }

    /**
     * @test
     */
    public function nabidkaNeobsahujePredmetPoUplynutiNabizetDo(): void
    {
        $uzivatel = $this->vytvorBeznehoUzivatele();
        $idPredmetu = $this->vytvorPredmet('Prošlá placka', nabizetDo: '2000-01-01 00:00:00');
        $idVerejneho = $this->vytvorPredmet('Veřejná placka');

        self::assertTrue($this->jeVidetVNabidce($uzivatel, $idVerejneho));
        self::assertFalse(
            $this->jeVidetVNabidce($uzivatel, $idPredmetu),
            'Předmět po uplynutí nabizet_do se nesmí nabízet k prodeji',
        );
    }
}
