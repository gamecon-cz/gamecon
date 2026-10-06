<?php

declare(strict_types=1);

namespace Gamecon\Tests\Uzivatel;

use Gamecon\Tests\Db\AbstractTestDb;

/**
 * Cookie „zůstat přihlášený" nese hodnotu uloženou i v DB. Odhlášení smaže cookie jen
 * v prohlížeči, takže její kopie musí přestat fungovat tím, že se vymění hodnota v DB.
 */
class TrvalePrihlaseniTest extends AbstractTestDb
{
    private const COOKIE = 'gcTrvalePrihlaseni';

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
        unset($_COOKIE[self::COOKIE], $_SESSION);
        if ($this->sessionIdNastavenoTady) {
            session_id('');
        }

        parent::tearDown();
    }

    public function testCookieSHodnotouZDbPrihlasiAZkopirovanaPoOdhlaseniUz(): void
    {
        $token = randHex(20);
        $idUzivatele = $this->vytvorUzivateleSTokenem($token);
        $_COOKIE[self::COOKIE] = $token;

        $this->bezVarovaniSession(static fn () => \Uzivatel::zId($idUzivatele)->odhlas(false));

        // Kopie cookie, kterou odhlášením nikdo nesmazal, protože ji prohlížeč uživatele neměl.
        $_COOKIE[self::COOKIE] = $token;
        unset($_SESSION);

        self::assertNull($this->bezVarovaniSession(static fn () => \Uzivatel::zSession()));
    }

    public function testOdhlaseniNastaviNovouNeprazdnouHodnotu(): void
    {
        $token = randHex(20);
        $idUzivatele = $this->vytvorUzivateleSTokenem($token);

        $this->bezVarovaniSession(static fn () => \Uzivatel::zId($idUzivatele)->odhlas(false));

        $po = dbOneCol('SELECT random FROM uzivatele_hodnoty WHERE id_uzivatele = $0', [$idUzivatele]);
        self::assertNotSame($token, $po);
        self::assertSame(20, strlen((string) $po));
    }

    public function testCookieSePouzijeBezOdhlaseniJakoDriv(): void
    {
        $token = randHex(20);
        $idUzivatele = $this->vytvorUzivateleSTokenem($token);
        $_COOKIE[self::COOKIE] = $token;

        $uzivatel = $this->bezVarovaniSession(static fn () => \Uzivatel::zSession());

        self::assertSame($idUzivatele, $uzivatel?->id());
    }

    public function testObnovaHeslaOdkazemZnehodnotiTrvalePrihlaseni(): void
    {
        $token = randHex(20);
        $idUzivatele = $this->vytvorUzivateleSTokenem($token);

        \Uzivatel::zId($idUzivatele)->heslo('nove-heslo-z-obnovy');

        $this->assertCookieUzNeprihlasi($token);
    }

    public function testZmenaHeslaVProfiluZnehodnotiTrvalePrihlaseni(): void
    {
        $token = randHex(20);
        $idUzivatele = $this->vytvorUzivateleSTokenem($token);

        \Uzivatel::zId($idUzivatele)->uprav([
            'heslo'          => 'nove-heslo-z-profilu',
            'heslo_kontrola' => 'nove-heslo-z-profilu',
        ]);

        $this->assertCookieUzNeprihlasi($token);
    }

    public function testUpravaJinychUdajuTrvalePrihlaseniNerusi(): void
    {
        $token = randHex(20);
        $idUzivatele = $this->vytvorUzivateleSTokenem($token);

        \Uzivatel::zId($idUzivatele)->uprav([
            'jmeno_uzivatele' => 'Jiné',
        ]);

        self::assertSame($token, dbOneCol('SELECT random FROM uzivatele_hodnoty WHERE id_uzivatele = $0', [$idUzivatele]));
    }

    /**
     * Přihlášení ukládá heslo znovu jen v novějším hashi; to není změna hesla, takže nesmí
     * shodit trvalé přihlášení na ostatních zařízeních.
     */
    public function testZpetnyZapisHasheAtPrihlaseniTokenNemeni(): void
    {
        $token = randHex(20);
        $idUzivatele = $this->vytvorUzivateleSTokenem($token);
        // Slabší cena než výchozí, takže `password_needs_rehash()` hash při přihlášení přepíše.
        $slabyHash = password_hash('stare-heslo', PASSWORD_BCRYPT, [
            'cost' => 4,
        ]);
        dbQuery('UPDATE uzivatele_hodnoty SET heslo_md5 = $0 WHERE id_uzivatele = $1', [$slabyHash, $idUzivatele]);
        $login = dbOneCol('SELECT login_uzivatele FROM uzivatele_hodnoty WHERE id_uzivatele = $0', [$idUzivatele]);

        $prihlaseny = $this->bezVarovaniSession(static fn () => \Uzivatel::prihlas($login, 'stare-heslo'));

        self::assertSame($idUzivatele, $prihlaseny?->id());
        self::assertNotSame(
            $slabyHash,
            dbOneCol('SELECT heslo_md5 FROM uzivatele_hodnoty WHERE id_uzivatele = $0', [$idUzivatele]),
            'Hash se při přihlášení má přepsat novějším, jinak test nic nezkouší.',
        );
        self::assertSame($token, dbOneCol('SELECT random FROM uzivatele_hodnoty WHERE id_uzivatele = $0', [$idUzivatele]));
    }

    private function assertCookieUzNeprihlasi(string $token): void
    {
        $_COOKIE[self::COOKIE] = $token;
        unset($_SESSION);

        self::assertNull($this->bezVarovaniSession(static fn () => \Uzivatel::zSession()));
    }

    /**
     * Sloupec `random` má 20 znaků, stejně jako `randHex(20)`; delší hodnota by se tiše oříznula
     * a test by nikdy nenašel, co hledá.
     */
    private function vytvorUzivateleSTokenem(string $token): int
    {
        self::assertSame(20, strlen($token));

        dbQuery(
            "INSERT INTO uzivatele_hodnoty SET
    login_uzivatele = $0,
    email1_uzivatele = $1,
    jmeno_uzivatele = 'Test',
    prijmeni_uzivatele = 'TrvalePrihlaseni',
    random = $2",
            [
                0 => 'test_trvale_' . uniqid('', false),
                1 => 'test.trvale.' . uniqid('', false) . '@example.org',
                2 => $token,
            ],
        );

        return (int) dbInsertId();
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
