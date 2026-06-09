<?php

declare(strict_types=1);

namespace Gamecon\Tests\Dev;

use Gamecon\Dev\ArchivSsoPrihlaseni;
use Gamecon\Dev\CrossSiteLogin;
use Gamecon\Tests\Db\AbstractUzivatelTestDb;
use Uzivatel;

/**
 * Integrační test ověřovací strany magického přihlášení do archivu: nad reálnou DB
 * a reálným {@see Uzivatel::prihlasId} ověřuje rozhodování {@see ArchivSsoPrihlaseni}.
 * Logika podpisu tokenu je v {@see CrossSiteLoginTest} (čistý unit).
 */
class ArchivSsoPrihlaseniTest extends AbstractUzivatelTestDb
{
    private const SECRET = 'integration-secret';
    private const NONCE = 'parovaci-nonce-integration';

    protected function tearDown(): void
    {
        // Session mezi testy nesmí přenášet přihlášení.
        \Uzivatel::odhlasKlic(\Uzivatel::UZIVATEL);
        parent::tearDown();
    }

    public function testPlatnyTokenSeShodnymNoncePrihlasiUzivatele(): void
    {
        $uzivatel = self::prihlasenyUzivatel();
        $token = CrossSiteLogin::podepis($uzivatel->mail(), self::NONCE, self::SECRET);

        $prihlaseny = (new ArchivSsoPrihlaseni(self::SECRET))->prihlas($token, self::NONCE, null);

        self::assertNotNull($prihlaseny);
        self::assertSame($uzivatel->id(), $prihlaseny->id());
        // Reálné přihlášení do session — zSession ho musí najít.
        $zeSession = \Uzivatel::zSession();
        self::assertNotNull($zeSession);
        self::assertSame($uzivatel->id(), $zeSession->id());
    }

    public function testNeshodnyNonceNeprihlasi(): void
    {
        $uzivatel = self::prihlasenyUzivatel();
        // Token nese self::NONCE, ale cookie přinese jiný → mismatch.
        $token = CrossSiteLogin::podepis($uzivatel->mail(), self::NONCE, self::SECRET);

        $prihlaseny = (new ArchivSsoPrihlaseni(self::SECRET))->prihlas($token, 'jiny-nonce', null);

        self::assertNull($prihlaseny);
        self::assertNull(\Uzivatel::zSession());
    }

    public function testChybejiciCookieNonceNeprihlasi(): void
    {
        $uzivatel = self::prihlasenyUzivatel();
        $token = CrossSiteLogin::podepis($uzivatel->mail(), self::NONCE, self::SECRET);

        $prihlaseny = (new ArchivSsoPrihlaseni(self::SECRET))->prihlas($token, null, null);

        self::assertNull($prihlaseny);
        self::assertNull(\Uzivatel::zSession());
    }

    public function testNeznamyEmailNeprihlasi(): void
    {
        // Platný token + shodný nonce, ale e-mail v téhle DB neexistuje.
        $token = CrossSiteLogin::podepis('nikdo-takovy@nikde.cz', self::NONCE, self::SECRET);

        $prihlaseny = (new ArchivSsoPrihlaseni(self::SECRET))->prihlas($token, self::NONCE, null);

        self::assertNull($prihlaseny);
        self::assertNull(\Uzivatel::zSession());
    }

    public function testPodvrzenyPodpisNeprihlasi(): void
    {
        $uzivatel = self::prihlasenyUzivatel();
        // Token podepsaný JINÝM secretem než kterým ho ověřujeme.
        $token = CrossSiteLogin::podepis($uzivatel->mail(), self::NONCE, 'utocnikuv-secret');

        $prihlaseny = (new ArchivSsoPrihlaseni(self::SECRET))->prihlas($token, self::NONCE, null);

        self::assertNull($prihlaseny);
        self::assertNull(\Uzivatel::zSession());
    }

    public function testJizPrihlasenehoNeprepise(): void
    {
        $puvodni = self::prihlasenyUzivatel();
        $jiny = self::prihlasenyUzivatel();
        // Token by přihlásil $jiny, ale $puvodni už je přihlášený → nepřepisujeme.
        $token = CrossSiteLogin::podepis($jiny->mail(), self::NONCE, self::SECRET);

        $vysledek = (new ArchivSsoPrihlaseni(self::SECRET))->prihlas($token, self::NONCE, $puvodni);

        self::assertSame($puvodni->id(), $vysledek->id());
        // Token se vůbec nevyhodnocoval, do session nic nepřibylo.
        self::assertNull(\Uzivatel::zSession());
    }

    public function testVyprsenyTokenNeprihlasi(): void
    {
        $uzivatel = self::prihlasenyUzivatel();
        // Podpis „v minulosti": expiry = TED + TTL je pořád před teď.
        $token = CrossSiteLogin::podepis($uzivatel->mail(), self::NONCE, self::SECRET, 1_700_000_000);

        $prihlaseny = (new ArchivSsoPrihlaseni(self::SECRET))->prihlas($token, self::NONCE, null);

        self::assertNull($prihlaseny);
        self::assertNull(\Uzivatel::zSession());
    }
}
