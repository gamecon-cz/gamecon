<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Finance;

use Gamecon\Finance\QrPlatba;
use PHPUnit\Framework\TestCase;

class QrPlatbaTest extends TestCase
{
    /**
     * @test
     */
    public function slovenskyQrPouzivaKomentarSVariabilnimSymbolemSOddelovacemDvojteckou(): void
    {
        $qrPlatba = QrPlatba::dejQrProSlovenskouPlatbu(10.5, 12345, IBAN);
        $metoda = new \ReflectionMethod($qrPlatba, 'slovenskaZpravaProPrijemce');
        $metoda->setAccessible(true);

        self::assertSame('VS:12345', $metoda->invoke($qrPlatba));
    }

    /**
     * @test
     */
    public function sepaQrPouzivaZpravuProPrijemceSVariabilnimSymbolemSOddelovacemDvojteckou(): void
    {
        $qrPlatba = QrPlatba::dejQrProSepaPlatbu(10.5, 12345);
        $metoda = new \ReflectionMethod($qrPlatba, 'sepaZpravaProPrijemce');
        $metoda->setAccessible(true);

        self::assertSame('VS:12345', $metoda->invoke($qrPlatba));
    }

    /**
     * Výpis ve `web/moduly/finance.php` zobrazuje `round($castkaEur, 2)`, QR kód
     * musí kódovat tutéž zaokrouhlenou částku, aby se zobrazená a placená částka
     * nerozcházely. Ověřujeme tedy, že QrPlatba zaokrouhlí vstup stejně jako výpis.
     *
     * @test
     * @dataProvider neceleEuroveCastky
     */
    public function qrKodZaokrouhliCastkuNaDveDesetinnaMistaShodneSVypisem(float $castkaEur): void
    {
        $qrPlatba = QrPlatba::dejQrProSepaPlatbu($castkaEur, 12345);
        $castkaVQr = (new \ReflectionProperty(QrPlatba::class, 'castka'))->getValue($qrPlatba);

        self::assertSame(round($castkaEur, 2), $castkaVQr);
    }

    /**
     * @return array<string, array{float}>
     */
    public static function neceleEuroveCastky(): array
    {
        return [
            'zaokrouhlení dolů'  => [41.32411],
            'zaokrouhlení nahoru' => [41.32987],
            'výsledek rezervy'   => [1000 / 24.2 * 1.015 + 0.25],
        ];
    }
}
