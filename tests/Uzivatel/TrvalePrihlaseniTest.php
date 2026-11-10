<?php

declare(strict_types=1);

namespace Gamecon\Tests\Uzivatel;

use App\Service\JwtService;
use Gamecon\Uzivatel\PrepnutiNaUzivatele;
use Gamecon\Uzivatel\SqlStruktura\UzivateleHodnotySqlStruktura as Sql;
use Gamecon\Uzivatel\TrvalePrihlaseni;

/**
 * Trvalé přihlášení má řádek na zařízení, takže přihlášení ani odhlášení na jednom zařízení
 * nesahá na ostatní. Běžící session ukončí až změna razítka přihlášení (`uzivatele_hodnoty.razitko_prihlaseni`),
 * které si session pamatuje od přihlášení: odhlášení ostatních zařízení a změna hesla.
 */
class TrvalePrihlaseniTest extends AbstractPrihlaseniNaZarizenichTestDb
{
    public function testCookiePrihlasiAVymeniToken(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $token = $this->zalozTrvalePrihlaseni($idUzivatele);
        $zarizeni = $this->zarizeni(cookie: $token);

        self::assertSame($idUzivatele, $this->idPrihlasenehoNa($zarizeni));
        self::assertNotSame($token, $zarizeni['cookie'], 'Přihlášení z cookie má token vyměnit.');

        $poRestartu = $this->poRestartuProhlizece($zarizeni);
        self::assertSame($idUzivatele, $this->idPrihlasenehoNa($poRestartu), 'Vyměněný token má přihlásit příště.');
    }

    public function testPrihlaseniNaDalsimZarizeniNeodhlasiPredchozi(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $prvni = $this->zarizeni(cookie: $this->zalozTrvalePrihlaseni($idUzivatele));
        $druhe = $this->zarizeni();
        $login = $this->login($idUzivatele);

        $this->pozadavek($druhe, static fn () => \Uzivatel::prihlasTrvale($login, self::HESLO));

        self::assertNotNull($druhe['cookie'], 'Trvalé přihlášení má dát prohlížeči cookie.');
        self::assertSame($idUzivatele, $this->idPrihlasenehoNa($prvni));
        $druhePoRestartu = $this->poRestartuProhlizece($druhe);
        self::assertSame($idUzivatele, $this->idPrihlasenehoNa($druhePoRestartu));
    }

    /**
     * Po „Přihlásit se jako“ (infopulťák vyplnil prezenci za vypravěče) se odhlašuje ten, kdo sedí u zařízení:
     * o trvalé přihlášení přijde jeho prohlížeč, vypravěč na svém telefonu zůstane přihlášený.
     */
    public function testOdhlaseniPoPrepnutiOdhlasiTohoKdoSePrepnul(): void
    {
        $idInfopultaka = $this->vytvorUzivatele();
        $idVypravece = $this->vytvorUzivatele();
        $infopult = $this->zarizeni(cookie: $this->zalozTrvalePrihlaseni($idInfopultaka));
        $telefonVypravece = $this->zarizeni(cookie: $this->zalozTrvalePrihlaseni($idVypravece));
        $this->idPrihlasenehoNa($telefonVypravece);
        $this->idPrihlasenehoNa($infopult);
        $cookieInfopultu = $infopult['cookie'];
        $this->pozadavek($infopult, static fn () => PrepnutiNaUzivatele::zSession()->prepni(
            \Uzivatel::zId($idInfopultaka),
            \Uzivatel::zId($idVypravece),
        ));

        $this->pozadavek($infopult, static fn () => \Uzivatel::zSession()->odhlas(false));

        self::assertNull($infopult['cookie'], 'Odhlášení má cookie infopulťáka smazat i v prohlížeči.');
        $kopie = $this->zarizeni(cookie: $cookieInfopultu);
        self::assertNull($this->idPrihlasenehoNa($kopie), 'Trvalé přihlášení infopulťáka má skončit.');
        self::assertSame($idVypravece, $this->idPrihlasenehoNa($telefonVypravece), 'Session vypravěče má běžet dál.');
        $telefonPoRestartu = $this->poRestartuProhlizece($telefonVypravece);
        self::assertSame($idVypravece, $this->idPrihlasenehoNa($telefonPoRestartu), 'Trvalé přihlášení vypravěče má zůstat.');
    }

    /**
     * Cookie patří tomu, kdo sedí u zařízení: nová session po ní nezačíná přepnutá na jiného uživatele.
     */
    public function testPrihlaseniCookieZapomenePrepnuti(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $zarizeni = $this->zarizeni(cookie: $this->zalozTrvalePrihlaseni($idUzivatele));

        $uzivatel = $this->pozadavek($zarizeni, static function (): ?\Uzivatel {
            PrepnutiNaUzivatele::zSession()->zaznamenej(\Uzivatel::SYSTEM);

            return \Uzivatel::zSession();
        });

        self::assertSame($idUzivatele, $uzivatel?->id());
        self::assertFalse((new PrepnutiNaUzivatele($zarizeni['session']))->isPrepnuto());
    }

    public function testOdhlaseniOdhlasiJenTotoZarizeni(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $token = $this->zalozTrvalePrihlaseni($idUzivatele);
        $toto = $this->zarizeni(cookie: $token);
        $jine = $this->zarizeni(cookie: $this->zalozTrvalePrihlaseni($idUzivatele));
        $this->idPrihlasenehoNa($jine);
        $this->idPrihlasenehoNa($toto);
        $vymenenyToken = $toto['cookie'];

        $this->pozadavek($toto, static fn () => \Uzivatel::zSession()->odhlas(false));

        self::assertNull($toto['cookie'], 'Odhlášení má cookie smazat i v prohlížeči.');
        // Kopie cookie, kterou odhlášení v prohlížeči smazat nemohlo, protože ji měl někdo jiný.
        foreach ([$token, $vymenenyToken] as $kopieCookie) {
            $kopie = $this->zarizeni(cookie: $kopieCookie);
            self::assertNull($this->idPrihlasenehoNa($kopie));
        }
        self::assertSame($idUzivatele, $this->idPrihlasenehoNa($jine), 'Session jiného zařízení má běžet dál.');
        $jinePoRestartu = $this->poRestartuProhlizece($jine);
        self::assertSame($idUzivatele, $this->idPrihlasenehoNa($jinePoRestartu), 'Trvalé přihlášení jiného zařízení má zůstat.');
    }

    public function testOdhlaseniOstatnichZarizeniUkonciJejichSessionITrvalePrihlaseni(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        [$toto, $jineSTrvalym, $jineJenSession] = $this->triPrihlasenaZarizeni($idUzivatele);

        $this->pozadavek($toto, static fn () => \Uzivatel::zSession()->odhlasNaOstatnichZarizenich());

        $this->assertOdhlasenaJenOstatniZarizeni($idUzivatele, $toto, $jineSTrvalym, $jineJenSession);
    }

    /**
     * Zbytek session by zdědil ten, kdo se na odhlášeném zařízení přihlásí příště, třeba na
     * sdíleném počítači infopultu.
     */
    public function testOdhlaseniOstatnichZarizeniZahodiCelouJejichSession(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $idPracovnihoUzivatele = $this->vytvorUzivatele();
        $toto = $this->zarizeni(cookie: $this->zalozTrvalePrihlaseni($idUzivatele));
        $this->idPrihlasenehoNa($toto);
        $jine = $this->zarizeni();
        $this->pozadavek($jine, static fn () => \Uzivatel::prihlasId($idUzivatele));
        $this->pozadavek($jine, static fn () => \Uzivatel::prihlasId($idPracovnihoUzivatele, \Uzivatel::UZIVATEL_PRACOVNI));

        $this->pozadavek($toto, static fn () => \Uzivatel::zSession()->odhlasNaOstatnichZarizenich());

        self::assertNull($this->idPrihlasenehoNa($jine));
        self::assertSame([], $jine['session']);
        self::assertNotEmpty($toto['session'], 'Session tohoto zařízení má zůstat.');
    }

    /**
     * Kdo ukradenou cookie použil první, drží vyměněný token téhož řádku, ze kterého má okradené
     * zařízení ten předchozí. Právě kvůli němu se ostatní zařízení odhlašují.
     */
    public function testOdhlaseniOstatnichZarizeniOdhlasiIZlodejeCookie(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $token = $this->zalozTrvalePrihlaseni($idUzivatele);
        $okradene = $this->zarizeni(cookie: $token);
        $this->pozadavek($okradene, static fn () => \Uzivatel::prihlasId($idUzivatele));
        $zlodej = $this->zarizeni(cookie: $token);
        self::assertSame($idUzivatele, $this->idPrihlasenehoNa($zlodej));
        $this->posunPouzitiDoMinulosti($idUzivatele, 120);

        $this->pozadavek($okradene, static fn () => \Uzivatel::zSession()->odhlasNaOstatnichZarizenich());

        $zlodejPoRestartu = $this->poRestartuProhlizece($zlodej);
        self::assertNull($this->idPrihlasenehoNa($zlodejPoRestartu), 'Vyměněný token zloděje nemá přihlásit.');
        self::assertNull($this->idPrihlasenehoNa($zlodej), 'Session zloděje má skončit.');
        self::assertSame($idUzivatele, $this->idPrihlasenehoNa($okradene));
    }

    /**
     * Přihlášení z cookie, které svůj řádek našlo těsně předtím, než ho odhlášení ostatních zařízení
     * smazalo, nesmí do session dostat nové razítko, jinak by přežilo.
     */
    public function testPrihlaseniZCookieSoubezneSOdhlasenimOstatnichNeprezije(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $toto = $this->zarizeni(cookie: $this->zalozTrvalePrihlaseni($idUzivatele));
        $this->idPrihlasenehoNa($toto);
        $jine = $this->zarizeni(cookie: $this->zalozTrvalePrihlaseni($idUzivatele));

        // Požadavek jiného zařízení: přečte cookie, mezitím doběhne odhlášení ostatních, pak teprve přihlásí.
        $trvalePrihlaseni = $this->pozadavek($jine, static fn () => TrvalePrihlaseni::uplatniCookie());
        self::assertNotNull($trvalePrihlaseni);
        $this->pozadavek($toto, static fn () => \Uzivatel::zSession()->odhlasNaOstatnichZarizenich());
        $this->pozadavek($jine, static fn () => \Uzivatel::prihlasTrvalymPrihlasenim($trvalePrihlaseni));

        self::assertNull($this->idPrihlasenehoNa($jine));
    }

    /**
     * Admin běží na jiném hostu než web a cookie trvalého přihlášení nevidí, s webem sdílí jen session.
     */
    public function testOdhlaseniVAdminuZrusiTrvalePrihlaseniZWebu(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $zCookie = $this->zarizeni(cookie: $this->zalozTrvalePrihlaseni($idUzivatele));
        $this->idPrihlasenehoNa($zCookie);
        $zFormulare = $this->zarizeni();
        $login = $this->login($idUzivatele);
        $this->pozadavek($zFormulare, static fn () => \Uzivatel::prihlasTrvale($login, self::HESLO));

        foreach ([$zCookie, $zFormulare] as $web) {
            $admin = $this->zarizeni(session: $web['session']);
            $this->pozadavek($admin, static fn () => \Uzivatel::zSession()->odhlas(false));

            $webPoRestartu = $this->poRestartuProhlizece($web);
            self::assertNull($this->idPrihlasenehoNa($webPoRestartu));
        }
    }

    /**
     * Starou kopii vyměněného tokenu může mít kdokoli, kdo cookie kdy viděl. Přihlášení ani odhlášení
     * s ní nesmí majiteli zrušit jeho trvalé přihlášení.
     */
    public function testStaryTokenNezrusiTrvalePrihlaseniMajitele(): void
    {
        $idMajitele = $this->vytvorUzivatele();
        $staryToken = $this->zalozTrvalePrihlaseni($idMajitele);
        $majitel = $this->zarizeni(cookie: $staryToken);
        $this->idPrihlasenehoNa($majitel);
        $this->posunPouzitiDoMinulosti($idMajitele, 120);
        $idCiziho = $this->vytvorUzivatele();
        $loginCiziho = $this->login($idCiziho);
        $prihlaseniSeStarymTokenem = $this->zarizeni(cookie: $staryToken);
        $odhlaseniSeStarymTokenem = $this->zarizeni(cookie: $staryToken);

        $this->pozadavek($prihlaseniSeStarymTokenem, static fn () => \Uzivatel::prihlasTrvale($loginCiziho, self::HESLO));
        $this->pozadavek($odhlaseniSeStarymTokenem, static fn () => \Uzivatel::prihlasId($idCiziho));
        $this->pozadavek($odhlaseniSeStarymTokenem, static fn () => \Uzivatel::zSession()->odhlas(false));

        $majitelPoRestartu = $this->poRestartuProhlizece($majitel);
        self::assertSame($idMajitele, $this->idPrihlasenehoNa($majitelPoRestartu));
    }

    /**
     * Tabulka může být chvíli prázdná, třeba během kopie databáze na betě. Smazaná cookie by trvalé
     * přihlášení zrušila napořád, i když se řádek s kopií vrátí.
     */
    public function testCookieBezRadkuZustaneVProhlizeci(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $token = $this->zalozTrvalePrihlaseni($idUzivatele);
        $radek = dbOneLine(<<<'SQL'
            SELECT * FROM uzivatele_trvala_prihlaseni WHERE id_uzivatele = $0
            SQL, [
            0 => $idUzivatele,
        ]);
        dbQuery(<<<'SQL'
            DELETE FROM uzivatele_trvala_prihlaseni WHERE id_uzivatele = $0
            SQL, [
            0 => $idUzivatele,
        ]);
        $zarizeni = $this->zarizeni(cookie: $token);

        self::assertNull($this->idPrihlasenehoNa($zarizeni));
        self::assertSame($token, $zarizeni['cookie']);

        dbInsert('uzivatele_trvala_prihlaseni', $radek);
        self::assertSame($idUzivatele, $this->idPrihlasenehoNa($zarizeni));
    }

    public function testZmenaHeslaVProfiluOdhlasiOstatniZarizeni(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        [$toto, $jineSTrvalym, $jineJenSession] = $this->triPrihlasenaZarizeni($idUzivatele);

        $this->pozadavek($toto, static fn () => \Uzivatel::zSession()->uprav([
            'heslo'          => 'nove-heslo-z-profilu',
            'heslo_kontrola' => 'nove-heslo-z-profilu',
        ]));

        $this->assertOdhlasenaJenOstatniZarizeni($idUzivatele, $toto, $jineSTrvalym, $jineJenSession);
    }

    public function testObnovaHeslaOdkazemOdhlasiVsechnaZarizeni(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        [$prvni, $druhe, $treti] = $this->triPrihlasenaZarizeni($idUzivatele);

        \Uzivatel::zId($idUzivatele)->heslo('nove-heslo-z-obnovy');

        foreach ([$prvni, $druhe, $treti] as $zarizeni) {
            self::assertNull($this->idPrihlasenehoNa($zarizeni));
        }
    }

    /**
     * Token pro e-shop je jeden na uživatele. Kdo ho ověřuje proti uloženému, přestane po odhlášení
     * ostatních zařízení brát jejich kopie.
     */
    public function testOdhlaseniOstatnichZarizeniVymeniUlozenyTokenProEshop(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $toto = $this->zarizeni();
        $this->pozadavek($toto, static fn () => \Uzivatel::prihlasId($idUzivatele));
        // Tokeny vydané v téže sekundě jsou stejné, token jiného zařízení proto podstrčíme.
        $this->jwtService()->storeToken('token-jineho-zarizeni', $idUzivatele);

        $this->pozadavek($toto, static fn () => \Uzivatel::zSession()->odhlasNaOstatnichZarizenich());

        $ulozeny = $this->jwtService()->getToken($idUzivatele);
        self::assertNotNull($ulozeny, 'Toto zařízení zůstalo přihlášené, token mu má zůstat.');
        self::assertNotSame('token-jineho-zarizeni', $ulozeny);
    }

    public function testZmenaHeslaVProfiluVymeniUlozenyTokenProEshop(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $toto = $this->zarizeni();
        $this->pozadavek($toto, static fn () => \Uzivatel::prihlasId($idUzivatele));
        $this->jwtService()->storeToken('token-jineho-zarizeni', $idUzivatele);

        $this->pozadavek($toto, static fn () => \Uzivatel::zSession()->uprav([
            'heslo'          => 'nove-heslo-z-profilu',
            'heslo_kontrola' => 'nove-heslo-z-profilu',
        ]));

        $ulozeny = $this->jwtService()->getToken($idUzivatele);
        self::assertNotNull($ulozeny, 'Toto zařízení zůstalo přihlášené, token mu má zůstat.');
        self::assertNotSame('token-jineho-zarizeni', $ulozeny);
    }

    /**
     * Po „Přihlásit se jako“ nese prohlížeč token pro e-shop toho, kdo se přepnul. Odhlášení ostatních zařízení
     * za druhého mu ho nesmí přepsat tokenem druhého, uložený token druhého jen smaže.
     */
    public function testOdhlaseniOstatnichZarizeniPoPrepnutiNevydaTokenProEshop(): void
    {
        $idInfopultaka = $this->vytvorUzivatele();
        $idVypravece = $this->vytvorUzivatele();
        $infopult = $this->zarizeni();
        $this->pozadavek($infopult, static fn () => \Uzivatel::prihlasId($idInfopultaka));
        $this->pozadavek($infopult, static fn () => PrepnutiNaUzivatele::zSession()->prepni(
            \Uzivatel::zId($idInfopultaka),
            \Uzivatel::zId($idVypravece),
        ));
        $this->jwtService()->storeToken('token-zarizeni-vypravece', $idVypravece);

        $this->pozadavek($infopult, static fn () => \Uzivatel::zSession()->odhlasNaOstatnichZarizenich());

        self::assertNull($this->jwtService()->getToken($idVypravece));
        self::assertSame($idVypravece, $this->idPrihlasenehoNa($infopult), 'Session přepnutého má běžet dál.');
    }

    /**
     * „Odhlásit" platí jen pro toto zařízení. Uložený token pro e-shop patří i ostatním.
     */
    public function testOdhlaseniNechaUlozenyTokenProEshopOstatnimZarizenim(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $toto = $this->zarizeni();
        $this->pozadavek($toto, static fn () => \Uzivatel::prihlasId($idUzivatele));
        $this->jwtService()->storeToken('token-jineho-zarizeni', $idUzivatele);

        $this->pozadavek($toto, static fn () => \Uzivatel::zSession()->odhlas(false));

        self::assertSame('token-jineho-zarizeni', $this->jwtService()->getToken($idUzivatele));
    }

    public function testObnovaHeslaSmazeUlozenyTokenProEshop(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $zarizeni = $this->zarizeni();
        $this->pozadavek($zarizeni, static fn () => \Uzivatel::prihlasId($idUzivatele));
        self::assertNotNull($this->jwtService()->getToken($idUzivatele), 'Přihlášení má token uložit, jinak test nic nezkouší.');

        \Uzivatel::zId($idUzivatele)->heslo('nove-heslo-z-obnovy');

        self::assertNull($this->jwtService()->getToken($idUzivatele));
    }

    /**
     * Sloučení převádí na nového uživatele všechno, co na starého odkazuje. Trvalé přihlášení ne:
     * zařízení starého by se přihlašovala jako nový bez jeho hesla.
     */
    public function testSlouceniNepreneseTrvalePrihlaseniSlucovanehoUctu(): void
    {
        $idNoveho = $this->vytvorUzivatele();
        $idStareho = $this->vytvorUzivatele();
        $zarizeniStareho = $this->zarizeni(cookie: $this->zalozTrvalePrihlaseni($idStareho));

        \Uzivatel::zId($idNoveho)->sluc(\Uzivatel::zId($idStareho));

        self::assertNull($this->idPrihlasenehoNa($zarizeniStareho));
    }

    public function testSlouceniSHeslemSlucovanehoUctuOdhlasiVsechnaZarizeni(): void
    {
        $idNoveho = $this->vytvorUzivatele();
        [$prvni, $druhe, $treti] = $this->triPrihlasenaZarizeni($idNoveho);
        $idStareho = $this->vytvorUzivatele();

        \Uzivatel::zId($idNoveho)->sluc(\Uzivatel::zId($idStareho), [Sql::HESLO_MD5]);

        foreach ([$prvni, $druhe, $treti] as $zarizeni) {
            self::assertNull($this->idPrihlasenehoNa($zarizeni));
        }
    }

    public function testUpravaJinychUdajuNikohoNeodhlasi(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        [$toto, $jineSTrvalym, $jineJenSession] = $this->triPrihlasenaZarizeni($idUzivatele);

        $this->pozadavek($toto, static fn () => \Uzivatel::zSession()->uprav([
            'jmeno_uzivatele' => 'Jiné',
        ]));

        foreach ([$toto, $jineSTrvalym, $jineJenSession] as $zarizeni) {
            self::assertSame($idUzivatele, $this->idPrihlasenehoNa($zarizeni));
        }
        $jinePoRestartu = $this->poRestartuProhlizece($jineSTrvalym);
        self::assertSame($idUzivatele, $this->idPrihlasenehoNa($jinePoRestartu));
    }

    /**
     * Přihlášení ukládá heslo znovu jen v novějším hashi; to není změna hesla, takže nesmí
     * odhlásit ostatní zařízení.
     */
    public function testZpetnyZapisHasheAtPrihlaseniNikohoNeodhlasi(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $jine = $this->zarizeni(cookie: $this->zalozTrvalePrihlaseni($idUzivatele));
        $this->idPrihlasenehoNa($jine);
        // Slabší cena než výchozí, takže `password_needs_rehash()` hash při přihlášení přepíše.
        $slabyHash = $this->hashHesla($idUzivatele);
        $login = $this->login($idUzivatele);
        $toto = $this->zarizeni();

        $prihlaseny = $this->pozadavek($toto, static fn () => \Uzivatel::prihlas($login, self::HESLO));

        self::assertSame($idUzivatele, $prihlaseny?->id());
        self::assertNotSame(
            $slabyHash,
            $this->hashHesla($idUzivatele),
            'Hash se při přihlášení má přepsat novějším, jinak test nic nezkouší.',
        );
        self::assertSame($idUzivatele, $this->idPrihlasenehoNa($jine));
        $jinePoRestartu = $this->poRestartuProhlizece($jine);
        self::assertSame($idUzivatele, $this->idPrihlasenehoNa($jinePoRestartu));
    }

    /**
     * Karty obnovené po spuštění prohlížeče se ptají naráz se stejnou cookie a token vymění jen první.
     */
    public function testSoubeznyPozadavekSPredchozimTokenemPrihlasiAPosleTentyzNovyToken(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $token = $this->zalozTrvalePrihlaseni($idUzivatele);
        $prvniKarta = $this->zarizeni(cookie: $token);
        $druhaKarta = $this->zarizeni(cookie: $token);

        self::assertSame($idUzivatele, $this->idPrihlasenehoNa($prvniKarta));
        self::assertSame($idUzivatele, $this->idPrihlasenehoNa($druhaKarta));

        self::assertNotSame($token, $prvniKarta['cookie'], 'První karta má token vyměnit, jinak test nic nezkouší.');
        self::assertSame($prvniKarta['cookie'], $druhaKarta['cookie'], 'Prohlížeč má nést nový token, ať uloží kteroukoli odpověď.');
        $poRestartu = $this->poRestartuProhlizece($prvniKarta);
        self::assertSame($idUzivatele, $this->idPrihlasenehoNa($poRestartu), 'Token z první karty má platit dál.');
    }

    /**
     * Odpověď, která token vyměnila, prohlížeč nemusí dostat (karta zavřená během obnovení, výpadek sítě).
     * Nový token mu pak pošle souběžná karta, jinak by se po minutě už nepřihlásil.
     */
    public function testZtracenouVymenuTokenuNahradiSoubeznaKarta(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $token = $this->zalozTrvalePrihlaseni($idUzivatele);
        $kartaSeZtracenouOdpovedi = $this->zarizeni(cookie: $token);
        $this->idPrihlasenehoNa($kartaSeZtracenouOdpovedi);
        $druhaKarta = $this->zarizeni(cookie: $token);
        $this->idPrihlasenehoNa($druhaKarta);
        $this->posunPouzitiDoMinulosti($idUzivatele, 120);

        $poRestartu = $this->poRestartuProhlizece($druhaKarta);
        self::assertSame($idUzivatele, $this->idPrihlasenehoNa($poRestartu));
    }

    /**
     * Kdo s ukradenou kopií přijde hned po majiteli, dostane tentýž nový token jako souběžná karta. Odhlášení
     * ostatních zařízení dá majiteli token, který se z ničeho neodvodí, a zloděje tak odhlásí taky.
     */
    public function testOdhlaseniOstatnichZarizeniOdhlasiIZlodejeSOdvozenymTokenem(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $token = $this->zalozTrvalePrihlaseni($idUzivatele);
        $okradene = $this->zarizeni(cookie: $token);
        $this->idPrihlasenehoNa($okradene);
        $zlodej = $this->zarizeni(cookie: $token);
        $this->idPrihlasenehoNa($zlodej);
        self::assertSame($okradene['cookie'], $zlodej['cookie'], 'Zloděj má dostat tentýž token, jinak test nic nezkouší.');

        $this->pozadavek($okradene, static fn () => \Uzivatel::zSession()->odhlasNaOstatnichZarizenich());

        self::assertNull($this->idPrihlasenehoNa($zlodej), 'Session ani token zloděje nemá přihlásit.');
        $okradenePoRestartu = $this->poRestartuProhlizece($okradene);
        self::assertSame($idUzivatele, $this->idPrihlasenehoNa($okradenePoRestartu), 'Majitel má zůstat trvale přihlášený.');
    }

    /**
     * Mezi ověřením hesla a založením trvalého přihlášení může majitel změnit heslo nebo odhlásit ostatní
     * zařízení. Trvalé přihlášení založené až po tom by je přežilo, s heslem, které už neplatí.
     */
    public function testTrvalePrihlaseniHeslemOverenymPredZmenouRazitkaNevznikne(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $login = $this->login($idUzivatele);
        $utocnik = $this->zarizeni();
        $prihlaseny = $this->pozadavek($utocnik, static fn () => \Uzivatel::prihlas($login, self::HESLO));
        $majitel = $this->zarizeni();
        $this->pozadavek($majitel, static fn () => \Uzivatel::prihlasId($idUzivatele));
        $this->pozadavek($majitel, static fn () => \Uzivatel::zSession()->odhlasNaOstatnichZarizenich());

        $idTrvalehoPrihlaseni = $this->pozadavek($utocnik, static fn () => TrvalePrihlaseni::zaloz(
            $idUzivatele,
            $prihlaseny->rawDb()[Sql::RAZITKO_PRIHLASENI],
            null,
        ));

        self::assertNull($idTrvalehoPrihlaseni);
        self::assertNull($utocnik['cookie']);
        self::assertSame(0, (int) dbOneCol(<<<'SQL'
            SELECT COUNT(*) FROM uzivatele_trvala_prihlaseni WHERE id_uzivatele = $0
            SQL, [
            0 => $idUzivatele,
        ]));
    }

    public function testPredchoziTokenPoChviliUzNeprihlasi(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $token = $this->zalozTrvalePrihlaseni($idUzivatele);
        $zarizeni = $this->zarizeni(cookie: $token);
        $this->idPrihlasenehoNa($zarizeni);
        $this->posunPouzitiDoMinulosti($idUzivatele, 120);

        $kopie = $this->zarizeni(cookie: $token);
        self::assertNull($this->idPrihlasenehoNa($kopie));
    }

    public function testTrvalePrihlaseniNepouziteRokVyprsi(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $zarizeni = $this->zarizeni(cookie: $this->zalozTrvalePrihlaseni($idUzivatele));
        $this->posunPouzitiDoMinulosti($idUzivatele, 366 * 24 * 3600);

        self::assertNull($this->idPrihlasenehoNa($zarizeni));
    }

    /**
     * Migrace založila řádek skoro každému účtu se stejným časem, takže za rok vyprší všechny naráz.
     * Smazat je jedním dotazem by zdrželo přihlášení toho, na koho to vyjde.
     */
    public function testTrvalePrihlaseniMazeVyprselaPoKouskach(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        dbQuery(<<<'SQL'
            INSERT INTO uzivatele_trvala_prihlaseni (id_uzivatele, token_hash, naposledy_pouzito)
            SELECT $0, SHA2(seq, 256), NOW() - INTERVAL 400 DAY
            FROM seq_1_to_1000
            SQL, [
            0 => $idUzivatele,
        ]);
        $login = $this->login($idUzivatele);
        $zarizeni = $this->zarizeni();

        $this->pozadavek($zarizeni, static fn () => \Uzivatel::prihlasTrvale($login, self::HESLO));

        $vyprselych = (int) dbOneCol(<<<'SQL'
            SELECT COUNT(*) FROM uzivatele_trvala_prihlaseni WHERE naposledy_pouzito < NOW() - INTERVAL 365 DAY
            SQL);
        self::assertGreaterThan(0, $vyprselych, 'Jedno přihlášení nemá mazat všechny vypršelé naráz.');
        self::assertLessThan(1000, $vyprselych, 'Přihlášení má vypršelé mazat.');
    }

    /**
     * Session z doby před razítkem žádné nemá a běží, dokud se uživatel neodhlásí na ostatních zařízeních.
     */
    public function testSessionBezRazitkaBeziDoOdhlaseniOstatnichZarizeni(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $stara = $this->zarizeni(session: [
            \Uzivatel::UZIVATEL => [
                'id_uzivatele' => $idUzivatele,
            ],
        ]);
        self::assertSame($idUzivatele, $this->idPrihlasenehoNa($stara));

        $toto = $this->zarizeni();
        $this->pozadavek($toto, static fn () => \Uzivatel::prihlasId($idUzivatele));
        $this->pozadavek($toto, static fn () => \Uzivatel::zSession()->odhlasNaOstatnichZarizenich());

        self::assertNull($this->idPrihlasenehoNa($stara));
    }

    /**
     * Migrace převedla tokeny z `random` hashem spočítaným v SQL, PHP ho musí počítat stejně.
     */
    public function testTokenPrevedenyMigraciPrihlasi(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $token = randHex(20);
        dbQuery(<<<'SQL'
            INSERT INTO uzivatele_trvala_prihlaseni SET id_uzivatele = $0, token_hash = SHA2($1, 256)
            SQL, [
            0 => $idUzivatele,
            1 => $token,
        ]);
        $zarizeni = $this->zarizeni(cookie: $token);

        self::assertSame($idUzivatele, $this->idPrihlasenehoNa($zarizeni));
    }

    /**
     * @return array{0: array, 1: array, 2: array} toto zařízení s trvalým přihlášením, jiné s trvalým
     *                                             a jiné jen se session, všechna se běžící session
     */
    private function triPrihlasenaZarizeni(int $idUzivatele): array
    {
        $toto = $this->zarizeni(cookie: $this->zalozTrvalePrihlaseni($idUzivatele));
        $jineSTrvalym = $this->zarizeni(cookie: $this->zalozTrvalePrihlaseni($idUzivatele));
        $jineJenSession = $this->zarizeni();
        $this->idPrihlasenehoNa($toto);
        $this->idPrihlasenehoNa($jineSTrvalym);
        $this->pozadavek($jineJenSession, static fn () => \Uzivatel::prihlasId($idUzivatele));

        return [$toto, $jineSTrvalym, $jineJenSession];
    }

    private function assertOdhlasenaJenOstatniZarizeni(
        int $idUzivatele,
        array $toto,
        array $jineSTrvalym,
        array $jineJenSession,
    ): void {
        self::assertSame($idUzivatele, $this->idPrihlasenehoNa($toto), 'Session tohoto zařízení má běžet dál.');
        $totoPoRestartu = $this->poRestartuProhlizece($toto);
        self::assertSame($idUzivatele, $this->idPrihlasenehoNa($totoPoRestartu), 'Trvalé přihlášení tohoto zařízení má zůstat.');
        self::assertNull($this->idPrihlasenehoNa($jineJenSession), 'Session jiného zařízení má skončit.');
        self::assertNull($this->idPrihlasenehoNa($jineSTrvalym), 'Jiné zařízení nemá přihlásit session ani cookie.');
        $jinePoRestartu = $this->poRestartuProhlizece($jineSTrvalym);
        self::assertNull($this->idPrihlasenehoNa($jinePoRestartu), 'Cookie jiného zařízení nemá přihlásit.');
    }

    private function zalozTrvalePrihlaseni(int $idUzivatele): string
    {
        $token = bin2hex(random_bytes(32));
        dbQuery(<<<'SQL'
            INSERT INTO uzivatele_trvala_prihlaseni SET id_uzivatele = $0, token_hash = $1
            SQL, [
            0 => $idUzivatele,
            1 => hash('sha256', $token),
        ]);

        return $token;
    }

    private function posunPouzitiDoMinulosti(int $idUzivatele, int $sekund): void
    {
        dbQuery(<<<'SQL'
            UPDATE uzivatele_trvala_prihlaseni SET naposledy_pouzito = NOW() - INTERVAL $1 SECOND WHERE id_uzivatele = $0
            SQL, [
            0 => $idUzivatele,
            1 => $sekund,
        ]);
    }

    private function hashHesla(int $idUzivatele): string
    {
        return (string) dbOneCol(<<<'SQL'
            SELECT heslo_md5 FROM uzivatele_hodnoty WHERE id_uzivatele = $0
            SQL, [
            0 => $idUzivatele,
        ]);
    }

    private function jwtService(): JwtService
    {
        return $this->getContainer()->get(JwtService::class);
    }
}
