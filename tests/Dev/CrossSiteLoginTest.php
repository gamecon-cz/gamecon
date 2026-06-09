<?php

declare(strict_types=1);

namespace Tests\Dev;

use Gamecon\Dev\CrossSiteLogin;
use PHPUnit\Framework\TestCase;

class CrossSiteLoginTest extends TestCase
{
    private const SECRET = 'test-secret-do-not-use-in-prod';
    private const TED = 1_700_000_000;

    public function testPrazdnySecretNepodepisuje(): void
    {
        self::assertSame('', CrossSiteLogin::podepis('a@b.cz', 'nonce', '', self::TED));
    }

    public function testPrazdnySecretNeoveruje(): void
    {
        $token = CrossSiteLogin::podepis('a@b.cz', 'nonce', self::SECRET, self::TED);
        self::assertNull(CrossSiteLogin::over($token, ''));
    }

    public function testRoundTripVratiStejnyEmailANonce(): void
    {
        // Bez explicitního času → expiry = now + TTL, tedy v budoucnosti (over()
        // odmítá vypršelé tokeny, viz testVyprsenyTokenNeprojde).
        $token = CrossSiteLogin::podepis('admin@gamecon.cz', 'nonce-123', self::SECRET);
        $overene = CrossSiteLogin::over($token, self::SECRET);

        self::assertNotNull($overene);
        self::assertSame('admin@gamecon.cz', $overene->email);
        self::assertSame('nonce-123', $overene->nonce);
    }

    public function testTokenMaTvarPayloadTeckaPodpisBezPaddingu(): void
    {
        $token = CrossSiteLogin::podepis('a@b.cz', 'nonce', self::SECRET, self::TED);

        self::assertStringContainsString('.', $token);
        // base64url: žádné +, /, ani = padding.
        self::assertDoesNotMatchRegularExpression('~[+/=]~', $token);
    }

    public function testZmenenyPodpisNeprojde(): void
    {
        $token = CrossSiteLogin::podepis('a@b.cz', 'nonce', self::SECRET, self::TED);
        [$payload, $podpis] = explode('.', $token, 2);
        // Otočíme první znak podpisu na jiný (stejně dlouhý) řetězec.
        $rozbity = $payload . '.' . ($podpis[0] === 'A' ? 'B' : 'A') . substr($podpis, 1);

        self::assertNull(CrossSiteLogin::over($rozbity, self::SECRET));
    }

    public function testZmenenyEmailVPayloaduNeprojde(): void
    {
        // Útočník chce přihlášení jako admin@gamecon.cz, ale podpis sedí na evil@x.cz.
        $token = CrossSiteLogin::podepis('evil@x.cz', 'nonce', self::SECRET, self::TED);
        [, $podpis] = explode('.', $token, 2);
        $podvrzenyPayload = rtrim(strtr(base64_encode('admin@gamecon.cz|' . (self::TED + 60) . '|nonce'), '+/', '-_'), '=');

        self::assertNull(CrossSiteLogin::over($podvrzenyPayload . '.' . $podpis, self::SECRET));
    }

    public function testVyprsenyTokenNeprojde(): void
    {
        // Podepíšeme „v minulosti": expiry = TED + TTL je pořád před aktuálním časem.
        $token = CrossSiteLogin::podepis('a@b.cz', 'nonce', self::SECRET, self::TED);
        self::assertNull(CrossSiteLogin::over($token, self::SECRET));
    }

    public function testCerstvyTokenProjde(): void
    {
        $token = CrossSiteLogin::podepis('a@b.cz', 'nonce', self::SECRET); // ted() = now
        self::assertNotNull(CrossSiteLogin::over($token, self::SECRET));
    }

    public function testJinySecretNeoveri(): void
    {
        $token = CrossSiteLogin::podepis('a@b.cz', 'nonce', 'secret-a');
        self::assertNull(CrossSiteLogin::over($token, 'secret-b'));
    }

    public function testNesmyslnyTvarTokenuVratiNull(): void
    {
        self::assertNull(CrossSiteLogin::over('bez-tecky', self::SECRET));
        self::assertNull(CrossSiteLogin::over('', self::SECRET));
        self::assertNull(CrossSiteLogin::over('....', self::SECRET));
    }

    public function testTokenZUrlSeDaOveritZpet(): void
    {
        // Mint přesně jako stare-rocniky.php: ?gcsso= připojené k /admin URL,
        // pak ho consume strana vytáhne z query a ověří.
        $nonce = 'parovaci-nonce-xyz';
        $gcsso = CrossSiteLogin::podepis('admin@gamecon.cz', $nonce, self::SECRET);
        $adminUrl = 'https://2021.gamecon.cz/admin?gcsso=' . $gcsso;

        parse_str((string) parse_url($adminUrl, PHP_URL_QUERY), $params);
        $overene = CrossSiteLogin::over($params['gcsso'], self::SECRET);

        self::assertNotNull($overene);
        self::assertSame('admin@gamecon.cz', $overene->email);
        self::assertSame($nonce, $overene->nonce);
    }

    public function testEmailSOddelovacemSeNepoplete(): void
    {
        // E-mail nesmí obsahovat '|', ale ověřme, že rozparsování je striktní na 3 části:
        // payload se třemi '|' (4 částmi) musí selhat, ne se tiše ořezat.
        $payload = rtrim(strtr(base64_encode('a@b.cz|x|' . (time() + 60) . '|nonce'), '+/', '-_'), '=');
        $podpis = rtrim(strtr(base64_encode(hash_hmac('sha256', base64_decode(strtr($payload, '-_', '+/'), true), self::SECRET, true)), '+/', '-_'), '=');

        self::assertNull(CrossSiteLogin::over($payload . '.' . $podpis, self::SECRET));
    }
}
