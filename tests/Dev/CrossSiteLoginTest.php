<?php

declare(strict_types=1);

namespace Tests\Dev;

use Gamecon\Dev\CrossSiteLogin;
use PHPUnit\Framework\TestCase;

class CrossSiteLoginTest extends TestCase
{
    private const SECRET = 'test-secret-do-not-use-in-prod';
    private const TED = 1_700_000_000;
    private const ID = 12345;

    public function testPrazdnySecretNepodepisuje(): void
    {
        self::assertSame('', CrossSiteLogin::podepis(self::ID, 'nonce', '', self::TED));
    }

    public function testPrazdnySecretNeoveruje(): void
    {
        $token = CrossSiteLogin::podepis(self::ID, 'nonce', self::SECRET, self::TED);
        self::assertNull(CrossSiteLogin::over($token, ''));
    }

    public function testRoundTripVratiStejneIdANonce(): void
    {
        // Bez explicitního času → expiry = now + TTL, tedy v budoucnosti (over()
        // odmítá vypršelé tokeny, viz testVyprsenyTokenNeprojde).
        $token = CrossSiteLogin::podepis(777, 'nonce-123', self::SECRET);
        $overene = CrossSiteLogin::over($token, self::SECRET);

        self::assertNotNull($overene);
        self::assertSame(777, $overene->idUzivatele);
        self::assertSame('nonce-123', $overene->nonce);
    }

    public function testTokenMaTvarPayloadTeckaPodpisBezPaddingu(): void
    {
        $token = CrossSiteLogin::podepis(self::ID, 'nonce', self::SECRET, self::TED);

        self::assertStringContainsString('.', $token);
        // base64url: žádné +, /, ani = padding.
        self::assertDoesNotMatchRegularExpression('~[+/=]~', $token);
    }

    public function testZmenenyPodpisNeprojde(): void
    {
        $token = CrossSiteLogin::podepis(self::ID, 'nonce', self::SECRET, self::TED);
        [$payload, $podpis] = explode('.', $token, 2);
        // Otočíme první znak podpisu na jiný (stejně dlouhý) řetězec.
        $rozbity = $payload . '.' . ($podpis[0] === 'A' ? 'B' : 'A') . substr($podpis, 1);

        self::assertNull(CrossSiteLogin::over($rozbity, self::SECRET));
    }

    public function testZmeneneIdVPayloaduNeprojde(): void
    {
        // Útočník chce přihlášení jako uživatel 1 (admin), ale podpis sedí na ID 9999.
        $token = CrossSiteLogin::podepis(9999, 'nonce', self::SECRET, self::TED);
        [, $podpis] = explode('.', $token, 2);
        $podvrzenyPayload = rtrim(strtr(base64_encode('1|' . (self::TED + 60) . '|nonce'), '+/', '-_'), '=');

        self::assertNull(CrossSiteLogin::over($podvrzenyPayload . '.' . $podpis, self::SECRET));
    }

    public function testVyprsenyTokenNeprojde(): void
    {
        // Podepíšeme „v minulosti": expiry = TED + TTL je pořád před aktuálním časem.
        $token = CrossSiteLogin::podepis(self::ID, 'nonce', self::SECRET, self::TED);
        self::assertNull(CrossSiteLogin::over($token, self::SECRET));
    }

    public function testCerstvyTokenProjde(): void
    {
        $token = CrossSiteLogin::podepis(self::ID, 'nonce', self::SECRET); // ted() = now
        self::assertNotNull(CrossSiteLogin::over($token, self::SECRET));
    }

    public function testJinySecretNeoveri(): void
    {
        $token = CrossSiteLogin::podepis(self::ID, 'nonce', 'secret-a');
        self::assertNull(CrossSiteLogin::over($token, 'secret-b'));
    }

    public function testKlicOdvozenyProRocnikNeoveriTokenJinehoRocniku(): void
    {
        // Izolace ročníků: ostrá podepisuje klíčem HMAC(rok, master); archiv ověřuje
        // jen svým odvozeným klíčem. Token pro 2025 NESMÍ projít klíčem 2024 —
        // popadený archiv 2025 tak neumí podvrhnout login do 2024.
        $master = 'master-sso-secret';
        $klic2025 = hash_hmac('sha256', '2025', $master);
        $klic2024 = hash_hmac('sha256', '2024', $master);

        $token2025 = CrossSiteLogin::podepis(self::ID, 'nonce', $klic2025);

        self::assertNotNull(CrossSiteLogin::over($token2025, $klic2025), 'vlastní ročník projde');
        self::assertNull(CrossSiteLogin::over($token2025, $klic2024), 'cizí ročník neprojde');
    }

    public function testNesmyslnyTvarTokenuVratiNull(): void
    {
        self::assertNull(CrossSiteLogin::over('bez-tecky', self::SECRET));
        self::assertNull(CrossSiteLogin::over('', self::SECRET));
        self::assertNull(CrossSiteLogin::over('....', self::SECRET));
    }

    public function testNeciselneIdVPayloaduNeprojde(): void
    {
        // payload musí nést ID jako číslice; podvržené ne-číselné ID (i s platným
        // podpisem) over() odmítne.
        $payload = 'neni-cislo|' . (time() + 60) . '|nonce';
        $payloadKodovany = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        $podpis = rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, self::SECRET, true)), '+/', '-_'), '=');

        self::assertNull(CrossSiteLogin::over($payloadKodovany . '.' . $podpis, self::SECRET));
    }

    public function testTokenZUrlSeDaOveritZpet(): void
    {
        // Mint přesně jako stare-rocniky.php: ?gcsso= připojené k /admin URL,
        // pak ho consume strana vytáhne z query a ověří.
        $nonce = 'parovaci-nonce-xyz';
        $gcsso = CrossSiteLogin::podepis(42, $nonce, self::SECRET);
        $adminUrl = 'https://2021.gamecon.cz/admin?gcsso=' . $gcsso;

        parse_str((string) parse_url($adminUrl, PHP_URL_QUERY), $params);
        $overene = CrossSiteLogin::over($params['gcsso'], self::SECRET);

        self::assertNotNull($overene);
        self::assertSame(42, $overene->idUzivatele);
        self::assertSame($nonce, $overene->nonce);
    }

    public function testPayloadSeCtyrmiCastmiNeprojde(): void
    {
        // Rozparsování je striktní na 3 části: payload se čtyřmi částmi musí selhat,
        // ne se tiše ořezat.
        $payload = '42|x|' . (time() + 60) . '|nonce';
        $payloadKodovany = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        $podpis = rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, self::SECRET, true)), '+/', '-_'), '=');

        self::assertNull(CrossSiteLogin::over($payloadKodovany . '.' . $podpis, self::SECRET));
    }
}
