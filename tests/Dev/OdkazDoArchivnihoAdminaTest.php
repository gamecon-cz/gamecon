<?php

declare(strict_types=1);

namespace Tests\Dev;

use Gamecon\Dev\Archive;
use Gamecon\Dev\CrossSiteLogin;
use Gamecon\Dev\OdkazDoArchivnihoAdmina;
use PHPUnit\Framework\TestCase;

class OdkazDoArchivnihoAdminaTest extends TestCase
{
    private const SSO_MASTER = 'test-master-do-not-use-in-prod';
    private const GATE_SECRET = 'test-gate-do-not-use-in-prod';
    private const NONCE = 'nonce-abc';
    private const ID_UZIVATELE = 102;
    private const ID_OPERATORA = 4032;

    /**
     * @param list<int> $roky
     */
    private function odkaz(
        array $roky = [2022, 2023, 2024, 2025],
        string $ssoMaster = self::SSO_MASTER,
        string $gateSecret = self::GATE_SECRET,
        ?string $nonce = self::NONCE,
        int $idOperatora = self::ID_OPERATORA,
    ): OdkazDoArchivnihoAdmina {
        $archivy = [];
        foreach ($roky as $rok) {
            $archivy[] = new Archive(
                year: $rok,
                url: "https://{$rok}.gamecon.cz",
                image: null,
                sha7: null,
                deployedAt: null,
            );
        }

        return new OdkazDoArchivnihoAdmina($archivy, $idOperatora, $ssoMaster, $gateSecret, $nonce);
    }

    public function testOdkazVedeNaCestuAdminNeNaSubdomenu(): void
    {
        $url = $this->odkaz()->proUzivatele(2023, self::ID_UZIVATELE);

        self::assertNotNull($url);
        self::assertStringStartsWith('https://2023.gamecon.cz/admin/uzivatel?', $url);
        self::assertStringNotContainsString('admin.2023.gamecon.cz', $url);
    }

    public function testOdkazNesePracovnihoUzivatele(): void
    {
        $url = $this->odkaz()->proUzivatele(2023, self::ID_UZIVATELE);

        self::assertStringContainsString('pracovni_uzivatel=' . self::ID_UZIVATELE, (string) $url);
    }

    public function testOdkazNeseSsoTokenPodepsanyKlicemRocniku(): void
    {
        $url = $this->odkaz()->proUzivatele(2023, self::ID_UZIVATELE);

        self::assertMatchesRegularExpression('~[?&]gcsso=~', (string) $url);

        parse_str((string) parse_url((string) $url, PHP_URL_QUERY), $query);
        $klicRocniku = hash_hmac('sha256', '2023', self::SSO_MASTER);
        $overene = CrossSiteLogin::over((string) $query['gcsso'], $klicRocniku);

        self::assertNotNull($overene, 'token musí projít ověřením klíčem odvozeným pro ročník');
        self::assertSame(self::NONCE, $overene->nonce);
    }

    public function testSsoPrihlasujeOperatoraNePracovnihoUzivatele(): void
    {
        // Dvě různé identity na jedné URL: gcsso = přihlášený admin, pracovni_uzivatel
        // = kdo se má otevřít. Záměna přihlásí admina do cizího účtu.
        $url = $this->odkaz()->proUzivatele(2023, self::ID_UZIVATELE);

        parse_str((string) parse_url((string) $url, PHP_URL_QUERY), $query);
        $klicRocniku = hash_hmac('sha256', '2023', self::SSO_MASTER);
        $overene = CrossSiteLogin::over((string) $query['gcsso'], $klicRocniku);

        self::assertNotNull($overene);
        self::assertSame(self::ID_OPERATORA, $overene->idUzivatele, 'token musí nést operátora');
        self::assertNotSame(self::ID_UZIVATELE, $overene->idUzivatele);
        self::assertSame((string) self::ID_UZIVATELE, $query['pracovni_uzivatel']);
    }

    public function testBezOperatoraSeSsoNepripoji(): void
    {
        // Nepřihlášený operátor (id 0) → nemáme koho přihlásit, token nedává smysl.
        $url = $this->odkaz(idOperatora: 0)->proUzivatele(2023, self::ID_UZIVATELE);

        self::assertNotNull($url);
        self::assertStringNotContainsString('gcsso=', $url);
        self::assertStringContainsString('pracovni_uzivatel=', $url);
    }

    public function testTokenJednohoRocnikuNeprojdeVJinemRocniku(): void
    {
        $url = $this->odkaz()->proUzivatele(2023, self::ID_UZIVATELE);
        parse_str((string) parse_url((string) $url, PHP_URL_QUERY), $query);

        $klicJinehoRocniku = hash_hmac('sha256', '2024', self::SSO_MASTER);

        self::assertNull(CrossSiteLogin::over((string) $query['gcsso'], $klicJinehoRocniku));
    }

    public function testOdkazNeseGateToken(): void
    {
        $url = $this->odkaz()->proUzivatele(2023, self::ID_UZIVATELE);

        self::assertMatchesRegularExpression('~[?&]gate=~', (string) $url);
    }

    public function testRocnikBezPodporyPracovnihoUzivateleVedeAsponDoAdmina(): void
    {
        // Archivy do 2021 parametr ?pracovni_uzivatel= vůbec nečtou. Odkaz ale
        // pořád ušetří hledání hostname a projde bránou, tak vede do adminu.
        $url = $this->odkaz([2021])->proUzivatele(2021, self::ID_UZIVATELE);

        self::assertNotNull($url);
        self::assertStringStartsWith('https://2021.gamecon.cz/admin?', $url);
        self::assertStringNotContainsString('pracovni_uzivatel', $url);
        self::assertStringContainsString('gcsso=', $url, '2021 SSO umí, takže přihlásit se má');
    }

    public function testRocnikBezSsoVedeDoAdminaBezPrihlaseni(): void
    {
        // 2012–2013 mají živý admin, ale magické přihlášení tam portované není.
        $url = $this->odkaz([2013])->proUzivatele(2013, self::ID_UZIVATELE);

        self::assertNotNull($url);
        self::assertStringStartsWith('https://2013.gamecon.cz/admin?', $url);
        self::assertStringNotContainsString('gcsso=', $url);
        self::assertStringContainsString('gate=', $url, 'branou musí projít i tak');
    }

    public function testRocnikBezZivehoAdminaNemaOdkaz(): void
    {
        // 2009–2010 jsou statické kopie z Internet Archive, admin tam není.
        $odkaz = $this->odkaz([2009, 2010, 2011]);

        self::assertNull($odkaz->proUzivatele(2009, self::ID_UZIVATELE));
        self::assertNull($odkaz->proUzivatele(2010, self::ID_UZIVATELE));
        self::assertNotNull($odkaz->proUzivatele(2011, self::ID_UZIVATELE));
    }

    public function testNenasazenyRocnikNemaOdkaz(): void
    {
        $odkaz = $this->odkaz([2023]);

        self::assertNull($odkaz->proUzivatele(2024, self::ID_UZIVATELE));
    }

    public function testBezSsoTajemstviZustaneOdkazBezPrihlaseni(): void
    {
        $url = $this->odkaz(ssoMaster: '')->proUzivatele(2023, self::ID_UZIVATELE);

        self::assertNotNull($url);
        self::assertStringNotContainsString('gcsso=', $url);
        self::assertStringContainsString('pracovni_uzivatel=', $url);
    }

    public function testBezNonceZustaneOdkazBezPrihlaseni(): void
    {
        $url = $this->odkaz(nonce: null)->proUzivatele(2023, self::ID_UZIVATELE);

        self::assertNotNull($url);
        self::assertStringNotContainsString('gcsso=', $url);
    }

    public function testBezGateTajemstviZustaneCistaUrl(): void
    {
        $url = $this->odkaz(gateSecret: '')->proUzivatele(2023, self::ID_UZIVATELE);

        self::assertNotNull($url);
        self::assertStringNotContainsString('gate=', $url);
    }
}
