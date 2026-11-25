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

    public function testSmazaniAktivityUvolniRezervaciAPosleEmail(): void
    {
        $this->rezervuj();
        $idAktivity = $this->aktivita->id();

        $this->aktivita->smaz($this->vypravec);

        self::assertSame([], self::rezervaceAktivity($idAktivity));
        self::assertSame(1, self::pocetEmailu($this->ucastnik, KodEmailoveSablonyEnum::KOSTYM_REZERVACE_UVOLNENA));
    }

    /**
     * E-mail o uvolnění odejde až po commitu smazání: o rezervaci, kterou rollback vrátí, nesmí.
     */
    public function testSpadleSmazaniAktivityRezervaciNechaAEmailNeposle(): void
    {
        $this->rezervuj();
        $idAktivity = $this->aktivita->id();
        // aktivita vede jinou instanci, než pod kterou tvrdí, že patří: smazání spadne až po odhlášení přihlášených
        $idJineAktivity = self::vlozAktivitu();
        dbQuery(<<<'SQL'
INSERT INTO akce_instance (id_hlavni_akce) VALUES ($0)
SQL, [$idAktivity]);
        dbQuery(<<<'SQL'
INSERT INTO akce_instance (id_hlavni_akce) VALUES ($0)
SQL, [$idJineAktivity]);
        dbQuery(<<<'SQL'
UPDATE akce_seznam SET patri_pod = $0 WHERE id_akce = $1
SQL, [(int) dbInsertId(), $idAktivity]);
        $aktivita = Aktivita::zId($idAktivity, false, self::systemoveNastaveniKCasu(self::TED));

        try {
            $aktivita->smaz($this->vypravec);
            self::fail('Smazání mělo spadnout na instanci, pod kterou aktivita nepatří');
        } catch (\RuntimeException $chyba) {
            self::assertStringContainsString('tvrdí, že patří pod instanci', $chyba->getMessage());
        }

        self::assertCount(1, self::rezervaceAktivity($idAktivity), 'Rollback rezervaci vrátil');
        self::assertSame(0, self::pocetEmailu($this->ucastnik, KodEmailoveSablonyEnum::KOSTYM_REZERVACE_UVOLNENA));
    }

    /**
     * Tak to dělá import účastníků: odhlášení v transakci, kterou pozdější chyba vrátí.
     */
    public function testOdlozenyEmailPoVracenePrihlasceNeodejde(): void
    {
        $this->rezervuj();

        try {
            PujcovnaKostymu::sOznamenimUvolneniPoZmene(function (): void {
                dbBegin();
                try {
                    $this->aktivita->odhlas($this->ucastnik, $this->vypravec, 'test');
                    throw new \Chyba('Import spadl na dalším řádku');
                } catch (\Throwable $chyba) {
                    dbRollback();
                    throw $chyba;
                }
            });
            self::fail('Změna měla spadnout');
        } catch (\Chyba) {
        }

        self::assertCount(1, self::rezervaceAktivity($this->aktivita->id()), 'Rollback rezervaci vrátil');
        self::assertSame(0, self::pocetEmailu($this->ucastnik, KodEmailoveSablonyEnum::KOSTYM_REZERVACE_UVOLNENA));
    }

    public function testRezervacePlatiIKdyzEmailNejdeOdeslat(): void
    {
        self::smazSablonu(KodEmailoveSablonyEnum::KOSTYM_REZERVACE_POTVRZENA);

        $this->rezervuj();

        self::assertSame(0, self::pocetEmailu($this->ucastnik, KodEmailoveSablonyEnum::KOSTYM_REZERVACE_POTVRZENA));
    }

    public function testOdhlaseniProjdeIKdyzEmailNejdeOdeslat(): void
    {
        $this->rezervuj();
        self::smazSablonu(KodEmailoveSablonyEnum::KOSTYM_REZERVACE_UVOLNENA);

        $this->aktivita->odhlas($this->ucastnik, $this->ucastnik, 'test');

        self::assertSame([], self::rezervaceAktivity($this->aktivita->id()));
        self::assertSame(0, self::pocetEmailu($this->ucastnik, KodEmailoveSablonyEnum::KOSTYM_REZERVACE_UVOLNENA));
    }

    /**
     * Bez šablony e-mail neodejde a vyhodí výjimku, stejně jako výpadek pošty (prázdný text tabulka nedovolí).
     */
    private static function smazSablonu(KodEmailoveSablonyEnum $sablona): void
    {
        dbQuery(<<<'SQL'
DELETE FROM emailova_sablona WHERE kod = $0
SQL, [$sablona->value]);
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
            (int) dbOneCol(<<<'SQL'
SELECT id_stavu_prihlaseni FROM akce_prihlaseni WHERE id_akce = $0 AND id_uzivatele = $1
SQL, [$this->aktivita->id(), $this->ucastnik->id()]),
        );
        self::assertCount(1, self::rezervaceAktivity($this->aktivita->id()));
    }
}
