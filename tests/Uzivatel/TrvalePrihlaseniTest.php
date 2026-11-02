<?php

declare(strict_types=1);

namespace Gamecon\Tests\Uzivatel;

use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\PrepnutiNaUzivatele;
use Gamecon\Uzivatel\SqlStruktura\UzivateleHodnotySqlStruktura as Sql;
use Gamecon\Uzivatel\TrvalePrihlaseni;

/**
 * Trvalé přihlášení má řádek na zařízení, takže přihlášení ani odhlášení na jednom zařízení
 * nesahá na ostatní. Běžící session ukončí až změna razítka přihlášení (`uzivatele_hodnoty.random`),
 * které si session pamatuje od přihlášení: odhlášení ostatních zařízení a změna hesla.
 *
 * Zařízení je tu session a cookie, se kterými prohlížeč přijde na server. {@see self::pozadavek()}
 * je nastaví a po požadavku uloží, co v nich zůstalo.
 */
class TrvalePrihlaseniTest extends AbstractTestDb
{
    private const string HESLO = 'heslo-trvale-prihlaseni';

    private bool $sessionIdNastavenoTady = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Funkce session v CLI po prvním výstupu testů hlásí varování, ale čtou $_SESSION i bez ní.
        if (session_id() === '') {
            session_id('trvale-prihlaseni-test');
            $this->sessionIdNastavenoTady = true;
        }
    }

    protected function tearDown(): void
    {
        unset($_COOKIE[TrvalePrihlaseni::COOKIE], $_SESSION);
        if ($this->sessionIdNastavenoTady) {
            session_id('');
        }

        parent::tearDown();
    }

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
        $this->pozadavek($jine, static fn () => \Uzivatel::prihlasId(
            $trvalePrihlaseni['id_uzivatele'],
            \Uzivatel::UZIVATEL,
            $trvalePrihlaseni['razitko'],
        ));

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
        $slabyHash = (string) dbOneCol('SELECT heslo_md5 FROM uzivatele_hodnoty WHERE id_uzivatele = $0', [$idUzivatele]);
        $login = $this->login($idUzivatele);
        $toto = $this->zarizeni();

        $prihlaseny = $this->pozadavek($toto, static fn () => \Uzivatel::prihlas($login, self::HESLO));

        self::assertSame($idUzivatele, $prihlaseny?->id());
        self::assertNotSame(
            $slabyHash,
            dbOneCol('SELECT heslo_md5 FROM uzivatele_hodnoty WHERE id_uzivatele = $0', [$idUzivatele]),
            'Hash se při přihlášení má přepsat novějším, jinak test nic nezkouší.',
        );
        self::assertSame($idUzivatele, $this->idPrihlasenehoNa($jine));
        $jinePoRestartu = $this->poRestartuProhlizece($jine);
        self::assertSame($idUzivatele, $this->idPrihlasenehoNa($jinePoRestartu));
    }

    /**
     * Karty obnovené po spuštění prohlížeče se ptají naráz se stejnou cookie a token vymění jen první.
     */
    public function testSoubeznyPozadavekSPredchozimTokenemPrihlasiATokenNechaByt(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $token = $this->zalozTrvalePrihlaseni($idUzivatele);
        $prvniKarta = $this->zarizeni(cookie: $token);
        $druhaKarta = $this->zarizeni(cookie: $token);

        self::assertSame($idUzivatele, $this->idPrihlasenehoNa($prvniKarta));
        self::assertSame($idUzivatele, $this->idPrihlasenehoNa($druhaKarta));

        self::assertSame($token, $druhaKarta['cookie'], 'Druhá karta nemá přepsat cookie, kterou poslala první.');
        $poRestartu = $this->poRestartuProhlizece($prvniKarta);
        self::assertSame($idUzivatele, $this->idPrihlasenehoNa($poRestartu), 'Token z první karty má platit dál.');
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
     * Nasazení nahraje kód dřív, než migrace vyprázdní `random`, kde je pořád token trvalého přihlášení.
     * Session, které přijdou mezitím, ani ty, které mezitím vzniknou, kvůli tomu skončit nesmí.
     */
    public function testSessionPrezijeNasazeniPredMigraci(): void
    {
        $idUzivatele = $this->vytvorUzivatele(random: randHex(20));
        $stara = $this->zarizeni(session: [
            \Uzivatel::UZIVATEL => [
                'id_uzivatele' => $idUzivatele,
            ],
        ]);
        $novaPredMigraci = $this->zarizeni();
        $this->pozadavek($novaPredMigraci, static fn () => \Uzivatel::prihlasId($idUzivatele));
        self::assertSame($idUzivatele, $this->idPrihlasenehoNa($stara));

        dbQuery(<<<'SQL'
            UPDATE uzivatele_hodnoty SET random = '' WHERE id_uzivatele = $0
            SQL, [
            0 => $idUzivatele,
        ]);

        self::assertSame($idUzivatele, $this->idPrihlasenehoNa($stara));
        self::assertSame($idUzivatele, $this->idPrihlasenehoNa($novaPredMigraci));
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
        self::assertNull($jineSTrvalym['cookie'], 'Zrušenou cookie má prohlížeči smazat.');
    }

    private function vytvorUzivatele(string $random = ''): int
    {
        dbQuery(<<<'SQL'
            INSERT INTO uzivatele_hodnoty SET
                login_uzivatele = $0,
                email1_uzivatele = $1,
                jmeno_uzivatele = 'Test',
                prijmeni_uzivatele = 'TrvalePrihlaseni',
                heslo_md5 = $2,
                random = $3
            SQL, [
            0 => 'test_trvale_' . uniqid('', true),
            1 => 'test.trvale.' . uniqid('', true) . '@example.org',
            // Slabší cena než výchozí: přihlášení je rychlé a hash přepíše.
            2 => password_hash(self::HESLO, PASSWORD_BCRYPT, [
                'cost' => 4,
            ]),
            3 => $random,
        ]);

        return (int) dbInsertId();
    }

    private function login(int $idUzivatele): string
    {
        return (string) dbOneCol('SELECT login_uzivatele FROM uzivatele_hodnoty WHERE id_uzivatele = $0', [$idUzivatele]);
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

    /**
     * @return array{session: array, cookie: ?string}
     */
    private function zarizeni(?string $cookie = null, array $session = []): array
    {
        return [
            'session' => $session,
            'cookie'  => $cookie,
        ];
    }

    /**
     * Session cookie zavřením prohlížeče zmizí, cookie trvalého přihlášení zůstane.
     */
    private function poRestartuProhlizece(array $zarizeni): array
    {
        return $this->zarizeni(cookie: $zarizeni['cookie']);
    }

    private function idPrihlasenehoNa(array &$zarizeni): ?int
    {
        return $this->pozadavek($zarizeni, static fn () => \Uzivatel::zSession())?->id();
    }

    private function pozadavek(array &$zarizeni, callable $akce): mixed
    {
        $_SESSION = $zarizeni['session'];
        unset($_COOKIE[TrvalePrihlaseni::COOKIE]);
        if ($zarizeni['cookie'] !== null) {
            $_COOKIE[TrvalePrihlaseni::COOKIE] = $zarizeni['cookie'];
        }
        try {
            return $this->bezVarovaniSession($akce);
        } finally {
            $zarizeni = $this->zarizeni(
                cookie: $_COOKIE[TrvalePrihlaseni::COOKIE] ?? null,
                session: $_SESSION ?? [],
            );
        }
    }

    /**
     * Session a cookie se v CLI po výstupu testovacího běhu nedají nastavit a hlásí to varováním,
     * což o ověřované věci nic neříká. Výjimky to nepotlačí.
     */
    private function bezVarovaniSession(callable $akce): mixed
    {
        set_error_handler(static fn (): bool => true, E_WARNING);
        try {
            return $akce();
        } finally {
            restore_error_handler();
        }
    }
}
