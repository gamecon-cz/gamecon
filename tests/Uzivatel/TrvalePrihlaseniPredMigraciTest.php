<?php

declare(strict_types=1);

namespace Gamecon\Tests\Uzivatel;

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Uzivatel\SqlStruktura\UzivateleHodnotySqlStruktura as Sql;

/**
 * Nasazení nahraje kód dřív, než migrace vytvoří tabulku trvalých přihlášení a sloupec razítka přihlášení.
 * Přihlášení, odhlášení, změny hesla ani registrace kvůli tomu mezitím spadnout nesmí.
 */
class TrvalePrihlaseniPredMigraciTest extends AbstractPrihlaseniNaZarizenichTestDb
{
    // DDL potvrdí transakci samo, vrátit ho nejde. Databáze se po třídě postaví znovu.
    protected static function keepTestClassDbChangesInTransaction(): bool
    {
        return false;
    }

    protected static function keepSingleTestMethodDbChangesInTransaction(): bool
    {
        return false;
    }

    protected static function resetDbAfterClass(): bool
    {
        return true;
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        try {
            dbQuery(<<<'SQL'
                DROP TABLE uzivatele_trvala_prihlaseni
                SQL);
            dbQuery(<<<'SQL'
                ALTER TABLE uzivatele_hodnoty DROP COLUMN razitko_prihlaseni
                SQL);
        } catch (\Throwable $throwable) {
            // PHPUnit po selhaném setUpBeforeClass() tearDownAfterClass() nevolá, databáze by zůstala bez tabulky
            static::tearDownAfterClass();
            throw $throwable;
        }
    }

    public function testTrvalePrihlaseniPrihlasiJenNaSession(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $login = $this->login($idUzivatele);
        $zarizeni = $this->zarizeni();

        $prihlaseny = $this->pozadavek($zarizeni, static fn () => \Uzivatel::prihlasTrvale($login, self::HESLO));

        self::assertSame($idUzivatele, $prihlaseny?->id());
        self::assertNull($zarizeni['cookie'], 'Bez tabulky nemá kam token uložit, cookie by nic nepřihlásila.');
        self::assertSame($idUzivatele, $this->idPrihlasenehoNa($zarizeni));
    }

    public function testCookieNeprihlasi(): void
    {
        $zarizeni = $this->zarizeni(cookie: bin2hex(random_bytes(32)));

        self::assertNull($this->idPrihlasenehoNa($zarizeni));
    }

    public function testOdhlaseniOdhlasi(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $zarizeni = $this->zarizeni(cookie: bin2hex(random_bytes(32)));
        $this->pozadavek($zarizeni, static fn () => \Uzivatel::prihlasId($idUzivatele));

        $this->pozadavek($zarizeni, static fn () => \Uzivatel::zSession()->odhlas(false));

        self::assertNull($zarizeni['cookie']);
        self::assertNull($this->idPrihlasenehoNa($zarizeni));
    }

    /**
     * Ostatní zařízení bez sloupce razítka odhlásit nejde, nové heslo se ale uložit musí.
     */
    public function testZmenaHeslaVProfiluHesloUlozi(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $toto = $this->zarizeni();
        $this->pozadavek($toto, static fn () => \Uzivatel::prihlasId($idUzivatele));

        $this->pozadavek($toto, static fn () => \Uzivatel::zSession()->uprav([
            'heslo'          => 'nove-heslo-z-profilu',
            'heslo_kontrola' => 'nove-heslo-z-profilu',
        ]));

        $login = $this->login($idUzivatele);
        $jine = $this->zarizeni();
        self::assertSame($idUzivatele, $this->pozadavek($jine, static fn () => \Uzivatel::prihlas($login, 'nove-heslo-z-profilu'))?->id());
    }

    public function testObnovaHeslaHesloUlozi(): void
    {
        $idUzivatele = $this->vytvorUzivatele();

        \Uzivatel::zId($idUzivatele)->heslo('nove-heslo-z-obnovy');

        $login = $this->login($idUzivatele);
        $zarizeni = $this->zarizeni();
        self::assertSame($idUzivatele, $this->pozadavek($zarizeni, static fn () => \Uzivatel::prihlas($login, 'nove-heslo-z-obnovy'))?->id());
    }

    public function testSlouceniSHeslemProjde(): void
    {
        $idNoveho = $this->vytvorUzivatele();
        $idStareho = $this->vytvorUzivatele();

        \Uzivatel::zId($idNoveho)->sluc(\Uzivatel::zId($idStareho), [Sql::HESLO_MD5]);

        self::assertNull(\Uzivatel::zId($idStareho));
    }

    public function testRychloregistraceProjde(): void
    {
        $idUzivatele = (int) \Uzivatel::rychloregistrace(SystemoveNastaveni::zGlobals());

        self::assertNotNull(\Uzivatel::zId($idUzivatele));
    }

    /**
     * Tlačítko nemá co vyměnit. Hláška „jsi odhlášen“ by lhala, chyba se dá za chvíli zopakovat.
     */
    public function testOdhlaseniOstatnichZarizeniSkonciChybou(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $toto = $this->zarizeni();
        $this->pozadavek($toto, static fn () => \Uzivatel::prihlasId($idUzivatele));

        $this->expectException(\DbException::class);
        $this->pozadavek($toto, static fn () => \Uzivatel::zSession()->odhlasNaOstatnichZarizenich());
    }
}
