<?php

declare(strict_types=1);

namespace Tests\Dev;

use Gamecon\Dev\CrossSiteLogin;
use Gamecon\Dev\VsevedaOdkaz;
use PHPUnit\Framework\TestCase;

class VsevedaOdkazTest extends TestCase
{
    private const VSEVEDA_SECRET = 'test-vseveda-do-not-use-in-prod';
    private const SSO_MASTER = 'test-master-do-not-use-in-prod';
    private const GATE_SECRET = 'test-gate-do-not-use-in-prod';
    private const NONCE = 'nonce-abc';
    private const ID_UZIVATELE = 4032;

    private function token(string $url): string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $parametry);

        return (string) $parametry['gcsso'];
    }

    public function testOdkazNeseTokenOveritelnyTajemstvimVsevedy(): void
    {
        $url = VsevedaOdkaz::url(self::ID_UZIVATELE, self::NONCE, self::VSEVEDA_SECRET, self::GATE_SECRET);

        self::assertNotNull($url);
        self::assertStringStartsWith(VsevedaOdkaz::URL_PRIHLASENI . '?gcsso=', $url);
        $overeno = CrossSiteLogin::over($this->token($url), self::VSEVEDA_SECRET);
        self::assertNotNull($overeno);
        self::assertSame(self::ID_UZIVATELE, $overeno->idUzivatele);
        self::assertSame(self::NONCE, $overeno->nonce);
    }

    public function testTokenPodepsanyMasteremPreviewDoVsevedyNeprojde(): void
    {
        $tokenZPreview = CrossSiteLogin::podepis(self::ID_UZIVATELE, self::NONCE, self::SSO_MASTER);

        self::assertNull(CrossSiteLogin::over($tokenZPreview, self::VSEVEDA_SECRET));
    }

    public function testOdkazProchaziBranou(): void
    {
        $url = (string) VsevedaOdkaz::url(self::ID_UZIVATELE, self::NONCE, self::VSEVEDA_SECRET, self::GATE_SECRET);

        self::assertMatchesRegularExpression('~[?&]gate=[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$~', $url);
    }

    public function testBezTajemstviOdkazNeni(): void
    {
        self::assertNull(VsevedaOdkaz::url(self::ID_UZIVATELE, self::NONCE, '', self::GATE_SECRET));
    }
}
