<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Uzivatel;

use Gamecon\Uzivatel\Finance;
use PHPUnit\Framework\TestCase;

/**
 * Instanci Finance vytvoříme bez konstruktoru a stav účtu nastavíme reflexí, takže přeskočíme přepočet z DB.
 */
class FinanceFormatovanyStavTest extends TestCase
{
    /**
     * @test
     */
    public function stavSHaleriSePiseSCarkou(): void
    {
        self::assertSame('-61,84&thinsp;Kč', $this->financeSeStavem(-61.84)->formatovanyStav());
    }

    /**
     * @test
     */
    public function celyStavSePiseBezDesetin(): void
    {
        self::assertSame('250&thinsp;Kč', $this->financeSeStavem(250.0)->formatovanyStav());
    }

    /**
     * @test
     */
    public function stavMimoHtmlMaObycejnouMezeru(): void
    {
        self::assertSame('-61,84 Kč', $this->financeSeStavem(-61.84)->formatovanyStav(false));
    }

    /**
     * @test
     */
    public function tisiceJsouOddeleneNezlomitelnouMezerou(): void
    {
        self::assertSame("1\u{a0}234,50&thinsp;Kč", $this->financeSeStavem(1234.5)->formatovanyStav());
        self::assertSame("1\u{a0}234,50 Kč", $this->financeSeStavem(1234.5)->formatovanyStav(false));
    }

    /**
     * @test
     */
    public function stavPodPulHalereSePiseJakoNula(): void
    {
        self::assertSame('0&thinsp;Kč', $this->financeSeStavem(-0.001)->formatovanyStav());
    }

    /**
     * @test
     */
    public function souctemHaleruNevznikaChybaPlovouciCarky(): void
    {
        self::assertSame('0,30&thinsp;Kč', $this->financeSeStavem(0.1 + 0.2)->formatovanyStav());
    }

    private function financeSeStavem(float $stav): Finance
    {
        $finance = (new \ReflectionClass(Finance::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(Finance::class, 'stav'))->setValue($finance, $stav);

        return $finance;
    }
}
