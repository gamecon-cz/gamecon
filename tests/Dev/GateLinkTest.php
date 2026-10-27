<?php

declare(strict_types=1);

namespace Tests\Dev;

use Gamecon\Dev\GateLink;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GateLinkTest extends TestCase
{
    private const string SECRET = 'test-secret-do-not-use-in-prod';

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

    public function testOverePlatnyTokenVytvorenyPodepis(): void
    {
        $ted = 1_700_000_000;
        $token = $this->vytahniGate(GateLink::podepis('https://host/', self::SECRET, $ted));

        self::assertTrue(GateLink::over($token, self::SECRET, $ted));
        self::assertTrue(GateLink::over($token, self::SECRET, $ted + GateLink::TTL_SEKUND - 1));
    }

    public function testOvereTokenVytvorenyJakoGoValidator(): void
    {
        // Token tak, jak ho skládá gate-validator pro X-Gate-Auth: expiry za minutu, base64url bez paddingu.
        $ted = 1_700_000_000;
        $token = self::tokenSExpiry($ted + 60, self::SECRET);

        self::assertTrue(GateLink::over($token, self::SECRET, $ted));
    }

    public function testNeovereVyprselyToken(): void
    {
        $ted = 1_700_000_000;
        $token = $this->vytahniGate(GateLink::podepis('https://host/', self::SECRET, $ted));

        self::assertFalse(GateLink::over($token, self::SECRET, $ted + GateLink::TTL_SEKUND));
        self::assertFalse(GateLink::over($token, self::SECRET, $ted + GateLink::TTL_SEKUND + 1));
    }

    /**
     * Pevný vektor spočítaný nezávisle (openssl dgst -sha256 -hmac nad ASCII číslicemi expiry, base64url),
     * stejný jako v testu gate-validatoru (Go) v repozitáři ansible. Hlídá, že si obě strany rozumí.
     */
    public function testOvereNezavisleSpocitanyVektorStejnyJakoVTestuValidatoru(): void
    {
        $vektor = 'MTcwMDAwMDA2MA.frH4LxPDGxoSb8OeQ0xQMKnZFijvkPODSFMJcZdTe_k';

        self::assertTrue(GateLink::over($vektor, self::SECRET, 1_700_000_000));
        self::assertFalse(GateLink::over($vektor, self::SECRET, 1_700_000_060));
    }

    public function testNeovereTokenPodepsanyJinymSecretem(): void
    {
        $ted = 1_700_000_000;

        self::assertFalse(GateLink::over(self::tokenSExpiry($ted + 60, 'jiny-secret'), self::SECRET, $ted));
    }

    public function testPrazdnySecretNeovereNic(): void
    {
        $ted = 1_700_000_000;

        self::assertFalse(GateLink::over(self::tokenSExpiry($ted + 60, ''), '', $ted));
    }

    public function testNejdelsiPlatnostOmezujeJakDalekoDoBudoucnaTokenSaha(): void
    {
        $ted = 1_700_000_000;
        $odkaz = $this->vytahniGate(GateLink::podepis('https://host/', self::SECRET, $ted));

        // Odkaz ze stránky Previews platí 24 h, volání brány jen minutu: delší token se pro ně nepřijme.
        self::assertFalse(GateLink::over($odkaz, self::SECRET, $ted, 120));
        self::assertTrue(GateLink::over($odkaz, self::SECRET, $ted));
        self::assertTrue(GateLink::over(self::tokenSExpiry($ted + 60, self::SECRET), self::SECRET, $ted, 120));
        self::assertTrue(GateLink::over(self::tokenSExpiry($ted + 120, self::SECRET), self::SECRET, $ted, 120));
        self::assertFalse(GateLink::over(self::tokenSExpiry($ted + 121, self::SECRET), self::SECRET, $ted, 120));
    }

    #[DataProvider('provideRozbityToken')]
    public function testNeovereRozbityToken(string $token): void
    {
        self::assertFalse(GateLink::over($token, self::SECRET, 1_700_000_000));
    }

    public static function provideRozbityToken(): iterable
    {
        $platny = self::tokenSExpiry(1_700_000_060, self::SECRET);
        [$expiryPart, $podpisPart] = explode('.', $platny, 2);

        yield 'prázdný' => [''];
        yield 'bez tečky' => ['bezTecky'];
        yield 'jen tečka' => ['.'];
        yield 'nesmysl' => ['not-base64!.also-bad!'];
        yield 'změněný podpis' => [$expiryPart . '.' . ($podpisPart[0] === 'A' ? 'B' : 'A') . substr($podpisPart, 1)];
        yield 'podpis jiného expiry' => [self::base64Url('1700009999') . '.' . $podpisPart];
        yield 'expiry není číslo' => [self::tokenSExpiryText('abc', self::SECRET)];
        yield 'záporné expiry' => [self::tokenSExpiryText('-1', self::SECRET)];
        yield 'expiry má jedenáct číslic' => [self::tokenSExpiryText('10000000000', self::SECRET)];
        yield 'expiry má dvacet číslic' => [self::tokenSExpiryText('99999999999999999999', self::SECRET)];
        yield 'největší int, který by nikdy nevypršel' => [self::tokenSExpiryText((string) PHP_INT_MAX, self::SECRET)];
        yield 'expiry nula' => [self::tokenSExpiryText('0', self::SECRET)];
        yield 'neplatná délka base64' => ['A.B'];
        yield 'podpis navíc' => [$platny . '.navic'];
        yield 'nový řádek v expiry' => [$expiryPart . "\n." . $podpisPart];
        yield 'mezera v podpisu' => [$expiryPart . '.' . substr($podpisPart, 0, 5) . ' ' . substr($podpisPart, 5)];
        yield 'nový řádek na konci' => [$platny . "\n"];
    }

    private static function tokenSExpiry(int $expiry, string $secret): string
    {
        return self::tokenSExpiryText((string) $expiry, $secret);
    }

    private static function tokenSExpiryText(string $expiry, string $secret): string
    {
        return self::base64Url($expiry) . '.' . self::base64Url(hash_hmac('sha256', $expiry, $secret, true));
    }

    private static function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
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
