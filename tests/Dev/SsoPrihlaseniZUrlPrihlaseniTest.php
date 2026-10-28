<?php

declare(strict_types=1);

namespace Gamecon\Tests\Dev;

use Gamecon\Dev\CrossSiteLogin;
use Gamecon\Dev\SsoPrihlaseniZUrl;
use Gamecon\Tests\Db\AbstractUzivatelTestDb;

/**
 * Integrační test spojení tokenu, klíče a cíle přesměrování v {@see SsoPrihlaseniZUrl::prihlasPodleTokenu}
 * nad reálnou DB. Samotná pravidla jsou v {@see SsoPrihlaseniZUrlTest} a {@see ArchivSsoPrihlaseniTest}.
 */
class SsoPrihlaseniZUrlPrihlaseniTest extends AbstractUzivatelTestDb
{
    private const string KLIC = 'integration-secret';
    private const string NONCE = 'parovaci-nonce-integration';
    private const string URL_BEZ_TOKENU = '/?gate=g';
    private const string NOUZOVA_URL = 'https://gamecon.example';

    protected function tearDown(): void
    {
        \Uzivatel::odhlasKlic(\Uzivatel::UZIVATEL);
        parent::tearDown();
    }

    public function testPlatnyTokenPrihlasiAVratiCilBezTokenu(): void
    {
        $uzivatel = self::prihlasenyUzivatel();
        $token = CrossSiteLogin::podepis($uzivatel->id(), self::NONCE, self::KLIC);

        $cil = $this->prihlasPodleTokenu($token, self::KLIC);

        self::assertSame(self::URL_BEZ_TOKENU, $cil);
        self::assertSame($uzivatel->id(), \Uzivatel::zSession()?->id());
    }

    public function testBezOverovacihoKliceSeNicNezpracuje(): void
    {
        $uzivatel = self::prihlasenyUzivatel();
        $token = CrossSiteLogin::podepis($uzivatel->id(), self::NONCE, self::KLIC);

        // Ostrá, beta a lokál: token je platný, ale není čím ho ověřit.
        self::assertNull($this->prihlasPodleTokenu($token, ''));
        self::assertNull(\Uzivatel::zSession());
    }

    public function testHodnotaKteraNeniTextSeNezpracuje(): void
    {
        self::assertNull($this->prihlasPodleTokenu(['x'], self::KLIC));
        self::assertNull($this->prihlasPodleTokenu('', self::KLIC));
        self::assertNull(\Uzivatel::zSession());
    }

    public function testNeplatnyTokenNeprihlasiAleVratiCilBezTokenu(): void
    {
        self::assertSame(self::URL_BEZ_TOKENU, $this->prihlasPodleTokenu('neplatny.token', self::KLIC));
        self::assertNull(\Uzivatel::zSession());
    }

    public function testCilNaJinyHostSeNahradiNouzovouUrl(): void
    {
        $uzivatel = self::prihlasenyUzivatel();
        $token = CrossSiteLogin::podepis($uzivatel->id(), self::NONCE, self::KLIC);

        $cil = $this->prihlasPodleTokenu($token, self::KLIC, '//jiny-host.example/');

        self::assertSame(self::NOUZOVA_URL, $cil);
    }

    public function testNonceZCookieMusiSedetSTokenem(): void
    {
        $uzivatel = self::prihlasenyUzivatel();
        $token = CrossSiteLogin::podepis($uzivatel->id(), self::NONCE, self::KLIC);

        self::assertSame(self::URL_BEZ_TOKENU, $this->prihlasPodleTokenu($token, self::KLIC, nonceZCookie: 'jiny-nonce'));
        self::assertSame(self::URL_BEZ_TOKENU, $this->prihlasPodleTokenu($token, self::KLIC, nonceZCookie: null));
        self::assertNull(\Uzivatel::zSession());
    }

    public function testJizPrihlasenehoNeprepise(): void
    {
        $puvodni = self::prihlasenyUzivatel();
        $jiny = self::prihlasenyUzivatel();
        $token = CrossSiteLogin::podepis($jiny->id(), self::NONCE, self::KLIC);

        $cil = $this->prihlasPodleTokenu($token, self::KLIC, prihlaseny: $puvodni);

        self::assertSame(self::URL_BEZ_TOKENU, $cil);
        self::assertNull(\Uzivatel::zSession());
    }

    private function prihlasPodleTokenu(
        mixed $token,
        string $overovaciKlic,
        string $urlBezTokenu = self::URL_BEZ_TOKENU,
        ?string $nonceZCookie = self::NONCE,
        ?\Uzivatel $prihlaseny = null,
    ): ?string {
        return SsoPrihlaseniZUrl::prihlasPodleTokenu(
            $token,
            $overovaciKlic,
            $nonceZCookie,
            $prihlaseny,
            $urlBezTokenu,
            self::NOUZOVA_URL,
        );
    }
}
