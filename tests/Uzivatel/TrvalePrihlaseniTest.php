<?php

declare(strict_types=1);

namespace Gamecon\Tests\Uzivatel;

use Gamecon\Tests\Db\AbstractTestDb;
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
        dbQuery(<<<'SQL'
            UPDATE uzivatele_trvala_prihlaseni SET naposledy_pouzito = NOW() - INTERVAL 2 MINUTE WHERE id_uzivatele = $0
            SQL, [
            0 => $idUzivatele,
        ]);

        $kopie = $this->zarizeni(cookie: $token);
        self::assertNull($this->idPrihlasenehoNa($kopie));
    }

    public function testTrvalePrihlaseniNepouziteRokVyprsi(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $zarizeni = $this->zarizeni(cookie: $this->zalozTrvalePrihlaseni($idUzivatele));
        dbQuery(<<<'SQL'
            UPDATE uzivatele_trvala_prihlaseni SET naposledy_pouzito = NOW() - INTERVAL 366 DAY WHERE id_uzivatele = $0
            SQL, [
            0 => $idUzivatele,
        ]);

        self::assertNull($this->idPrihlasenehoNa($zarizeni));
    }

    /**
     * Session z doby před razítkem žádné nemá. Migrace nastavila `random` všem na prázdné,
     * takže taková session běží, dokud se uživatel neodhlásí na ostatních zařízeních.
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
    }

    private function vytvorUzivatele(): int
    {
        dbQuery(<<<'SQL'
            INSERT INTO uzivatele_hodnoty SET
                login_uzivatele = $0,
                email1_uzivatele = $1,
                jmeno_uzivatele = 'Test',
                prijmeni_uzivatele = 'TrvalePrihlaseni',
                heslo_md5 = $2,
                random = ''
            SQL, [
            0 => 'test_trvale_' . uniqid('', true),
            1 => 'test.trvale.' . uniqid('', true) . '@example.org',
            // Slabší cena než výchozí: přihlášení je rychlé a hash přepíše.
            2 => password_hash(self::HESLO, PASSWORD_BCRYPT, [
                'cost' => 4,
            ]),
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
