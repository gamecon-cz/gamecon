<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Uzivatel;

use Gamecon\Uzivatel\Finance;
use PHPUnit\Framework\TestCase;

/**
 * Regresní testy částky pro QR platbu v eurech.
 *
 * Instanci Finance vytvoříme bez konstruktoru a stav účtu nastavíme reflexí,
 * takže přeskočíme přepočet z DB a test zůstává čistě jednotkový. Konstantu
 * KURZ_EURO (v ostrém běhu pochází ze systémového nastavení) si pro účely testu
 * nadefinujeme na pevnou hodnotu, pokud ještě definovaná není.
 */
class FinanceCastkaProQrPlatbuTest extends TestCase
{
    private const TESTOVACI_KURZ_EURO = 25.0;

    public static function setUpBeforeClass(): void
    {
        if (! \defined('KURZ_EURO')) {
            \define('KURZ_EURO', self::TESTOVACI_KURZ_EURO);
        }
    }

    private function financeSeStavem(float $stav): Finance
    {
        $finance = (new \ReflectionClass(Finance::class))->newInstanceWithoutConstructor();
        $stavVlastnost = new \ReflectionProperty(Finance::class, 'stav');
        $stavVlastnost->setValue($finance, $stav);

        return $finance;
    }

    private function konstanta(string $nazev): float
    {
        return (new \ReflectionClass(Finance::class))->getConstant($nazev);
    }

    /**
     * KURZ_EURO je v ostrém běhu „magická" konstanta ze systémového nastavení,
     * kterou statická analýza nezná. Čteme ji přes constant() (vrací float po
     * přetypování), ať test zůstává PHPStan-čistý a nezávisí na bareword konstantě.
     */
    private function kurzEuro(): float
    {
        return (float) \constant('KURZ_EURO');
    }

    /**
     * @test
     */
    public function eurovaCastkaObsahujeKurzovouRezervu(): void
    {
        $dluhKc = 1000.0;
        $finance = $this->financeSeStavem(-$dluhKc);

        $nasobek = $this->konstanta('KURZOVA_REZERVA_EUR_NASOBEK');
        $fixni = $this->konstanta('KURZOVA_REZERVA_EUR_FIXNI');
        $ocekavano = $dluhKc / $this->kurzEuro() * $nasobek + $fixni;

        self::assertEqualsWithDelta($ocekavano, $finance->dejCastkuProQrPlatbuVEurech(), 0.0001);
    }

    /**
     * Bez rezervy by holý převod dluhu nemusel po směně zpět na koruny dluh dorovnat;
     * ověřujeme, že výsledek je o rezervu vyšší než prostý převod.
     *
     * @test
     */
    public function eurovaCastkaSRezervouJeVyssiNezProstyPrevod(): void
    {
        $finance = $this->financeSeStavem(-1000.0);

        $prostyPrevod = 1000.0 / $this->kurzEuro();

        self::assertGreaterThan($prostyPrevod, $finance->dejCastkuProQrPlatbuVEurech());
    }

    /**
     * Když uživatel nic nedluží (vyrovnaný či kladný stav), nabízíme jen minimální
     * dobrovolnou částku a rezervu nepřičítáme.
     *
     * @test
     */
    public function bezDluhuJeEurovaCastkaMinimalniBezRezervy(): void
    {
        self::assertSame(0.1, $this->financeSeStavem(0.0)->dejCastkuProQrPlatbuVEurech());
        self::assertSame(0.1, $this->financeSeStavem(250.0)->dejCastkuProQrPlatbuVEurech());
    }
}
