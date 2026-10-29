<?php

declare(strict_types=1);

namespace Gamecon\Tests\Dev;

use Gamecon\Dev\CrossSiteLogin;
use Gamecon\Dev\GateLink;
use Gamecon\Dev\PreviewVstup;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PreviewVstupTest extends TestCase
{
    private const string GATE_SECRET = 'gate-secret';
    private const string SSO_MASTER = 'sso-master';
    private const string NONCE = '0123456789abcdef0123456789abcdef';
    private const int TED = 1_700_000_000;
    private const int ID_UZIVATELE = 4242;

    public function testVolaniSPlatnymTokenemJeOdValidatoru(): void
    {
        self::assertTrue(self::vstup()->jeVolaniOdValidatoru(self::gateAuth(self::TED + 60), self::TED));
    }

    #[DataProvider('provideNeplatneGateAuth')]
    public function testVolaniBezPlatnehoTokenuNeniOdValidatoru(string $gateAuth): void
    {
        self::assertFalse(self::vstup()->jeVolaniOdValidatoru($gateAuth, self::TED));
    }

    public function testVolaniPlatiAzDoNejdelsiPlatnostiTokenu(): void
    {
        $nejdelsi = PreviewVstup::NEJDELSI_PLATNOST_TOKENU_SEKUND;

        self::assertTrue(self::vstup()->jeVolaniOdValidatoru(self::gateAuth(self::TED + 60), self::TED));
        self::assertTrue(self::vstup()->jeVolaniOdValidatoru(self::gateAuth(self::TED + $nejdelsi), self::TED));
        self::assertFalse(self::vstup()->jeVolaniOdValidatoru(self::gateAuth(self::TED + $nejdelsi + 1), self::TED));
    }

    public function testOdkazGateNeboCookieGcGateNeniVolaniOdValidatoru(): void
    {
        // Odkaz ?gate= ze stránky Previews i cookie gc_gate platí 24 h a drží je každý, kdo byl v preview.
        $odkaz = GateLink::podepis('https://host/', self::GATE_SECRET, self::TED);
        parse_str((string) parse_url($odkaz, PHP_URL_QUERY), $query);

        self::assertFalse(self::vstup()->jeVolaniOdValidatoru($query['gate'], self::TED));
    }

    public static function provideNeplatneGateAuth(): iterable
    {
        yield 'bez hlavičky' => [''];
        yield 'platí déle než minutu' => [self::gateAuth(self::TED + 600)];
        yield 'vypršelý' => [self::gateAuth(self::TED - 1)];
        yield 'jiný secret' => [self::gateAuth(self::TED + 60, 'jiny-secret')];
        yield 'nesmysl' => ['not-a-token'];
    }

    public function testBezGateSecretuNeniNikdoValidator(): void
    {
        $vstup = new PreviewVstup('', self::SSO_MASTER);

        self::assertFalse($vstup->jeVolaniOdValidatoru(self::gateAuth(self::TED + 60, ''), self::TED));
    }

    public function testNeprihlaseneMuNerekneNicVic(): void
    {
        [$status, $telo] = self::vstup()->odpovez(null, false, false, self::NONCE, self::TED);

        self::assertSame(401, $status);
        self::assertSame([
            'stav' => 'neprihlasen',
        ], $telo);
    }

    public function testPrihlaseneBezOpravneniOdmitne(): void
    {
        // Běžný účastník: relace je stejná jako organizátorova, liší se jen rolemi.
        [$status, $telo] = self::vstup()->odpovez(self::ID_UZIVATELE, false, false, self::NONCE, self::TED);

        self::assertSame(403, $status);
        self::assertSame([
            'stav' => 'bez-pristupu',
        ], $telo);
    }

    #[DataProvider('provideOpravneni')]
    public function testOrganizatorNeboSpravceWebuDostaneLoginToken(bool $jeOrganizator, bool $maPravoNaWeb): void
    {
        // CrossSiteLogin::over() hlídá platnost podle skutečných hodin, token se tu proto podepisuje teď.
        [$status, $telo] = self::vstup()->odpovez(self::ID_UZIVATELE, $jeOrganizator, $maPravoNaWeb, self::NONCE);

        self::assertSame(200, $status);
        self::assertSame('ok', $telo['stav']);

        $overene = CrossSiteLogin::over($telo['gcsso'], self::SSO_MASTER);
        self::assertNotNull($overene);
        self::assertSame(self::ID_UZIVATELE, $overene->idUzivatele);
        self::assertSame(self::NONCE, $overene->nonce);
    }

    public static function provideOpravneni(): iterable
    {
        yield 'organizátor' => [true, false];
        yield 'správce webu' => [false, true];
        yield 'obojí' => [true, true];
    }

    #[DataProvider('provideNonceBezLoginu')]
    public function testBezPlatnehoNonceNeboMasteruJenPustiDal(string $nonce, string $master): void
    {
        [$status, $telo] = (new PreviewVstup(self::GATE_SECRET, $master))
            ->odpovez(self::ID_UZIVATELE, true, false, $nonce, self::TED);

        self::assertSame(200, $status);
        self::assertSame([
            'stav'  => 'ok',
            'gcsso' => '',
        ], $telo);
    }

    public static function provideNonceBezLoginu(): iterable
    {
        yield 'bez nonce' => ['', self::SSO_MASTER];
        yield 'krátké nonce' => ['abc', self::SSO_MASTER];
        yield 'velká písmena' => [strtoupper(self::NONCE), self::SSO_MASTER];
        yield 'nový řádek na konci' => [self::NONCE . "\n", self::SSO_MASTER];
        yield 'bez masteru' => [self::NONCE, ''];
    }

    private static function vstup(): PreviewVstup
    {
        return new PreviewVstup(self::GATE_SECRET, self::SSO_MASTER);
    }

    /**
     * Tak, jak ho skládá gate-validator (Go): base64url(expiry) "." base64url(HMAC-SHA256(expiry, secret)).
     */
    private static function gateAuth(int $expiry, string $secret = self::GATE_SECRET): string
    {
        $base64Url = static fn (string $data): string => rtrim(strtr(base64_encode($data), '+/', '-_'), '=');

        return $base64Url((string) $expiry) . '.' . $base64Url(hash_hmac('sha256', (string) $expiry, $secret, true));
    }
}
