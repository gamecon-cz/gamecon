<?php

declare(strict_types=1);

namespace Gamecon\Tests\Kostymy;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Kanaly\EmailovaSablona\KodEmailoveSablonyEnum;
use Gamecon\Kostymy\PujcovnaKostymu;
use Gamecon\Tests\Db\AbstractUzivatelTestDb;

/**
 * Rezervace zmizí s přihláškou, ať ji smaže kterákoli cesta. E-mail pošle jen odhlášení.
 * Rozpuštění týmu (Doctrine spojení) je v @see PujcovnaKostymuRozpusteniTymuTest
 */
class PujcovnaKostymuUvolneniTest extends AbstractUzivatelTestDb
{
    use KostymyTestTrait;

    private const string TED = '2099-01-01 06:00:00';

    protected static bool $disableStrictTransTables = true;

    private Aktivita $aktivita;
    private \Uzivatel $ucastnik;
    private \Uzivatel $vypravec;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ucastnik = self::prihlasenyUzivatel();
        $this->vypravec = self::prihlasenyUzivatel();
        $idAktivity = self::vlozAktivitu();
        self::vlozPrihlasku($idAktivity, $this->ucastnik->id());
        $this->aktivita = Aktivita::zId($idAktivity, false, self::systemoveNastaveniKCasu(self::TED));
    }

    private function pujcovna(): PujcovnaKostymu
    {
        return new PujcovnaKostymu(self::systemoveNastaveniKCasu(self::TED), self::nastaveniPujcovny());
    }

    private function rezervuj(): void
    {
        $this->pujcovna()->rezervuj(self::vlozKostym(), $this->aktivita, $this->ucastnik, $this->ucastnik);
        self::assertCount(1, self::rezervaceAktivity($this->aktivita->id()));
    }

    public function testRezervacePosleEmailSPotvrzenim(): void
    {
        $this->rezervuj();

        self::assertSame(1, self::pocetEmailu($this->ucastnik, KodEmailoveSablonyEnum::KOSTYM_REZERVACE_POTVRZENA));
    }

    public function testOdhlaseniUvolniRezervaciAPosleEmail(): void
    {
        $this->rezervuj();

        $this->aktivita->odhlas($this->ucastnik, $this->ucastnik, 'test');

        self::assertSame([], self::rezervaceAktivity($this->aktivita->id()));
        self::assertSame(1, self::pocetEmailu($this->ucastnik, KodEmailoveSablonyEnum::KOSTYM_REZERVACE_UVOLNENA));
    }

    public function testOdhlaseniBezRezervaceEmailNeposle(): void
    {
        $this->aktivita->odhlas($this->ucastnik, $this->ucastnik, 'test');

        self::assertSame(0, self::pocetEmailu($this->ucastnik, KodEmailoveSablonyEnum::KOSTYM_REZERVACE_UVOLNENA));
    }

    public function testNedorazilUvolniRezervaciBezEmailu(): void
    {
        $this->rezervuj();

        $this->aktivita->dejPrezenci()->ulozNedorazivsiho($this->ucastnik, $this->vypravec);

        self::assertSame([], self::rezervaceAktivity($this->aktivita->id()));
        self::assertSame(0, self::pocetEmailu($this->ucastnik, KodEmailoveSablonyEnum::KOSTYM_REZERVACE_UVOLNENA));
    }

    public function testZrusenyNahradnikPrijdeORezervaci(): void
    {
        // náhradník se přihlásí až v prezenci, rezervaci tu drží jen databáze
        $nahradnik = self::prihlasenyUzivatel();
        self::vlozPrihlasku($this->aktivita->id(), $nahradnik->id(), StavPrihlaseni::DORAZIL_JAKO_NAHRADNIK);
        self::vlozRezervaci(self::vlozKostym(), $this->aktivita->id(), $nahradnik->id());

        $this->aktivita->dejPrezenci()->zrusZeDorazil($nahradnik, $this->vypravec);

        self::assertSame([], self::rezervaceAktivity($this->aktivita->id()));
    }

    public function testPotvrzeniZeDorazilRezervaciNecha(): void
    {
        $this->rezervuj();

        $this->aktivita->dejPrezenci()->ulozZeDorazil($this->ucastnik, $this->vypravec);

        self::assertSame(
            StavPrihlaseni::PRIHLASEN_A_DORAZIL,
            (int) dbOneCol('SELECT id_stavu_prihlaseni FROM akce_prihlaseni WHERE id_akce = $0 AND id_uzivatele = $1', [$this->aktivita->id(), $this->ucastnik->id()]),
        );
        self::assertCount(1, self::rezervaceAktivity($this->aktivita->id()));
    }
}
