<?php

declare(strict_types=1);

namespace Tests\Dev;

use Gamecon\Dev\GateLink;
use PHPUnit\Framework\TestCase;

class GateLinkTest extends TestCase
{
    private const SECRET = 'test-secret-do-not-use-in-prod';

    public function testPrazdnySecretVraciUrlBeZeZmeny(): void
    {
        $url = 'https://2024.gamecon.cz/';
        self::assertSame($url, GateLink::podepis($url, '', 1_700_000_000));
    }

    public function testPripojiGateParametrSOtaznikem(): void
    {
        $podepsana = GateLink::podepis('https://2024.gamecon.cz/', self::SECRET, 1_700_000_000);
        self::assertStringStartsWith('https://2024.gamecon.cz/?gate=', $podepsana);
    }

    public function testPripojiGateParametrSAmpersandemKdyzUrlJizMaQuery(): void
    {
        $podepsana = GateLink::podepis('https://2024.gamecon.cz/?foo=bar', self::SECRET, 1_700_000_000);
        self::assertStringContainsString('?foo=bar&gate=', $podepsana);
    }

    public function testTokenMaTvarExpiryTeckaPodpis(): void
    {
        $podepsana = GateLink::podepis('https://host/', self::SECRET, 1_700_000_000);
        $gate = $this->vytahniGate($podepsana);

        self::assertStringContainsString('.', $gate);
        [$expiryPart, $podpisPart] = explode('.', $gate, 2);
        self::assertNotSame('', $expiryPart);
        self::assertNotSame('', $podpisPart);
        // base64url: žádné +, /, ani = padding.
        self::assertDoesNotMatchRegularExpression('~[+/=]~', $gate);
    }

    public function testExpiryJeCasPlusTtl(): void
    {
        $ted = 1_700_000_000;
        $podepsana = GateLink::podepis('https://host/', self::SECRET, $ted);
        $gate = $this->vytahniGate($podepsana);

        [$expiryPart] = explode('.', $gate, 2);
        $expiry = self::base64UrlDecode($expiryPart);

        self::assertSame((string) ($ted + GateLink::TTL_SEKUND), $expiry);
    }

    /**
     * Interop: token MUSÍ být ověřitelný stejným HMAC výpočtem, jaký dělá
     * gate-validator (Go) — HMAC-SHA256 nad ASCII bajty expiry, base64url.
     * Tady přepočítáme očekávaný podpis nezávisle a porovnáme.
     */
    public function testPodpisOdpovidaNezavislemuHmacVypoctu(): void
    {
        $ted = 1_700_000_000;
        $expiry = (string) ($ted + GateLink::TTL_SEKUND);
        $podepsana = GateLink::podepis('https://host/', self::SECRET, $ted);
        $gate = $this->vytahniGate($podepsana);

        [$expiryPart, $podpisPart] = explode('.', $gate, 2);

        // Nezávislý přepočet (jako validator): HMAC nad dekódovaným expiry.
        $expiryBytes = self::base64UrlDecode($expiryPart);
        $ocekavanyPodpis = hash_hmac('sha256', $expiryBytes, self::SECRET, true);
        $skutecnyPodpis = self::base64UrlDecode($podpisPart);

        self::assertSame($expiry, $expiryBytes);
        self::assertTrue(hash_equals($ocekavanyPodpis, $skutecnyPodpis));
    }

    public function testRuznySecretDavaRuznyPodpis(): void
    {
        $ted = 1_700_000_000;
        $a = GateLink::podepis('https://host/', 'secret-a', $ted);
        $b = GateLink::podepis('https://host/', 'secret-b', $ted);
        self::assertNotSame($a, $b);
    }

    private function vytahniGate(string $url): string
    {
        $query = parse_url($url, PHP_URL_QUERY);
        parse_str((string) $query, $params);
        self::assertArrayHasKey('gate', $params);

        return $params['gate'];
    }

    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'), true);
    }
}
