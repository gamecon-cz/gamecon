<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Uzivatel;

use Gamecon\Jazyk\AktualniJazyk;
use Gamecon\Jazyk\Jazyk;
use Gamecon\Uzivatel\Finance;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FinanceFormatujCastkuTest extends TestCase
{
    /**
     * @return array<string, array{float, string}>
     */
    public static function provideCastky(): array
    {
        return [
            'celé koruny bez desetin'      => [250.0, '250'],
            'záporné celé koruny'          => [-150.0, '-150'],
            'nula'                         => [0.0, '0'],
            'záporná nula po zaokrouhlení' => [-0.001, '0'],
            'haléře s čárkou'              => [250.6, '250,60'],
            'záporné haléře'               => [-12.34, '-12,34'],
            'dvě desetinná místa nahoru'   => [0.125, '0,13'],
            'plovoucí čárka nevadí'        => [0.1 + 0.2, '0,30'],
            'zaokrouhlení na celou korunu' => [99.999, '100'],
            'tisíce s pevnou mezerou'      => [1234.5, "1\u{a0}234,50"],
            'celé tisíce'                  => [1000.0, "1\u{a0}000"],
            'miliony záporně'              => [-1234567.89, "-1\u{a0}234\u{a0}567,89"],
        ];
    }

    #[DataProvider('provideCastky')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function castkaSeZapiseJakoCeleKorunyNeboSHaleri(float $castka, string $ocekavano): void
    {
        self::assertSame($ocekavano, Finance::formatujCastku($castka));
    }

    /**
     * @return array<string, array{float, string}>
     */
    public static function provideAnglickeCastky(): array
    {
        return [
            'celé koruny bez desetin'      => [250.0, '250'],
            'haléře s tečkou'              => [250.6, '250.60'],
            'záporná nula po zaokrouhlení' => [-0.001, '0'],
            'tisíce s čárkou'              => [1234.5, '1,234.50'],
            'miliony záporně'              => [-1234567.89, '-1,234,567.89'],
        ];
    }

    #[DataProvider('provideAnglickeCastky')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function anglickyJeTeckaACarkaMeziTisici(float $castka, string $ocekavano): void
    {
        self::assertSame($ocekavano, Finance::formatujCastku($castka, Jazyk::ANGLICTINA));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function bezJazykaJeCastkaCeskyIVAnglickemPozadavku(): void
    {
        // jde i do e-mailů a adminu, ty jsou česky i z anglické stránky
        self::assertSame("1\u{a0}234,50", AktualniJazyk::provedVJazyce(Jazyk::ANGLICTINA, static fn () => Finance::formatujCastku(1234.5)));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function castkaSMenouVJazyceStranky(): void
    {
        self::assertSame("1\u{a0}234,50&thinsp;Kč", Finance::formatujCastkuVKorunachHtml(1234.5));
        self::assertSame('41,32&thinsp;€', Finance::formatujCastkuVEurechHtml(41.32));
        self::assertSame('CZK&nbsp;1,234.50', Finance::formatujCastkuVKorunachHtml(1234.5, Jazyk::ANGLICTINA));
        self::assertSame('€41.32', Finance::formatujCastkuVEurechHtml(41.32, Jazyk::ANGLICTINA));
        self::assertSame('CZK&nbsp;250', AktualniJazyk::provedVJazyce(Jazyk::ANGLICTINA, static fn () => Finance::formatujCastkuVKorunachHtml(250.0)));
    }
}
