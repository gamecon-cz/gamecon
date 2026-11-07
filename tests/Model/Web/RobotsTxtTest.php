<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use Gamecon\Prostredi\Prostredi;
use Gamecon\Web\RobotsTxt;
use PHPUnit\Framework\TestCase;

class RobotsTxtTest extends TestCase
{
    /**
     * @test
     */
    public function naOstreSeProchazeniPovolujeKromeStranekProPrihlasene(): void
    {
        $obsah = (new RobotsTxt(Prostredi::Production))->obsah();

        self::assertStringContainsString("User-agent: *\n", $obsah);
        self::assertStringContainsString("Disallow: /finance$\n", $obsah);
        self::assertStringContainsString("Disallow: /finance/\n", $obsah);
        self::assertStringContainsString("Disallow: /qrka$\n", $obsah);
        self::assertStringContainsString("Disallow: /qrka/\n", $obsah);
        self::assertStringContainsString("Disallow: /api/\n", $obsah);
        self::assertDoesNotMatchRegularExpression('~^Disallow: /$~m', $obsah);
    }

    /**
     * @test
     *
     * @dataProvider provideProstrediBezIndexace
     */
    public function mimoOstrouSeProchazeniZakazujeCele(Prostredi $prostredi): void
    {
        $obsah = (new RobotsTxt($prostredi))->obsah();

        self::assertSame("User-agent: *\nDisallow: /\n", $obsah);
    }

    public static function provideProstrediBezIndexace(): array
    {
        return [
            'beta'    => [Prostredi::Beta],
            'preview' => [Prostredi::Preview],
            'lokalne' => [Prostredi::Locale],
        ];
    }
}
