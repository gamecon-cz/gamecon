<?php

declare(strict_types=1);

namespace Gamecon\Tests\Dev;

use Gamecon\Dev\SsoParovaciCookie;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SsoParovaciCookieTest extends TestCase
{
    private const string NONCE = '0123456789abcdef0123456789abcdef';

    public function testPlatnyNonceZCookieSePrevezme(): void
    {
        self::assertSame(self::NONCE, SsoParovaciCookie::getNonceProKliknuti(self::NONCE));
    }

    #[DataProvider('provideNeplatnyNonce')]
    public function testNeplatnyNonceSeNahradiNovym(?string $nonceZCookie): void
    {
        $nonce = SsoParovaciCookie::getNonceProKliknuti($nonceZCookie);

        self::assertMatchesRegularExpression('~^[0-9a-f]{32}$~', $nonce);
        self::assertNotSame($nonceZCookie, $nonce);
    }

    public static function provideNeplatnyNonce(): iterable
    {
        yield 'bez cookie' => [null];
        yield 'příliš krátký' => ['abc123'];
        yield 'o znak delší' => [self::NONCE . 'a'];
        yield 'nový řádek na konci' => [self::NONCE . "\n"];
        yield 'velká písmena' => [strtoupper(self::NONCE)];
        yield 'oddělovač tokenu uvnitř' => ['0123456789abcdef|123456789abcdef'];
    }

    public function testNovyNonceJeKazdyKratJiny(): void
    {
        self::assertNotSame(
            SsoParovaciCookie::getNonceProKliknuti(null),
            SsoParovaciCookie::getNonceProKliknuti(null),
        );
    }
}
