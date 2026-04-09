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
    public function sepaQrPouzivaReferenciSVariabilnimSymbolemBezOddelovace(): void
    {
        $qrPlatba = QrPlatba::dejQrProSepaPlatbu(10.5, 12345);
        $metoda = new \ReflectionMethod($qrPlatba, 'sepaReferencePlatby');
        $metoda->setAccessible(true);

        self::assertSame('VS12345', $metoda->invoke($qrPlatba));
    }
}
