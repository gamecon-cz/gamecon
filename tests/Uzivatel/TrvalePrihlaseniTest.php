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
