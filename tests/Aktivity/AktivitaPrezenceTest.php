<?php

declare(strict_types=1);

namespace Gamecon\Tests\Aktivity;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\AktivitaPrezence;
use Gamecon\Aktivita\AktivitaPrezenceTyp;
use Gamecon\Aktivita\StavAktivity;
use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Aktivita\ZmenaPrihlaseni;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractUzivatelTestDb;

class AktivitaPrezenceTest extends AbstractUzivatelTestDb
{
    use ProbihaRegistraceAktivitTrait;

    private ?SystemoveNastaveni $systemoveNastaveni = null;
    private ?Aktivita $aktivita = null;
    private ?\Uzivatel $ucastnik = null;
    private ?\Uzivatel $vypravec = null;

    protected static bool $disableStrictTransTables = true;

    protected static function resetDbAfterClass(): bool
    {
        return true;
    }

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->systemoveNastaveni = self::vytvorSystemoveNastaveni();

            dbInsert('akce_seznam', [
                'stav'     => StavAktivity::AKTIVOVANA,
                'typ'      => 1,
                'teamova'  => 0,
                'kapacita' => 10,
                'zacatek'  => '2099-01-01 08:00:00',
                'konec'    => '2099-01-01 14:00:00',
                'rok'      => ROCNIK,
            ]);
            $idAktivity = dbInsertId();

            $this->aktivita = Aktivita::zId($idAktivity, false, $this->systemoveNastaveni);
            self::assertNotNull($this->aktivita, "Aktivita s ID {$idAktivity} musí existovat");

            $this->ucastnik = self::prihlasenyUzivatel();
            $this->vypravec = self::prihlasenyUzivatel();
        } catch (\Throwable $throwable) {
            $this->tearDown();
            throw $throwable;
        }
    }

    private function prezence(): AktivitaPrezence
    {
        return $this->aktivita->dejPrezenci();
    }

    private function prihlasUcastnika(\Uzivatel $ucastnik): void
    {
        $this->aktivita->prihlas($ucastnik, $this->vypravec, Aktivita::UKAZAT_DETAILY_CHYBY);
        $this->aktivita->refresh();
    }

    private function dejStavPrihlaseniZDb(\Uzivatel $ucastnik): ?int
    {
        $radek = dbOneLine(
            'SELECT id_stavu_prihlaseni FROM akce_prihlaseni WHERE id_akce = $1 AND id_uzivatele = $2',
            [$this->aktivita->id(), $ucastnik->id()],
        );

        return $radek ? (int) $radek['id_stavu_prihlaseni'] : null;
    }

    private function dejPosledniLogTyp(\Uzivatel $ucastnik): ?string
    {
        $radek = dbOneLine(
            'SELECT typ FROM akce_prihlaseni_log WHERE id_akce = $1 AND id_uzivatele = $2 ORDER BY id_log DESC LIMIT 1',
            [$this->aktivita->id(), $ucastnik->id()],
        );

        return $radek ? $radek['typ'] : null;
    }

    private function dejPocetLogu(\Uzivatel $ucastnik): int
    {
        $radek = dbOneLine(
            'SELECT COUNT(*) AS pocet FROM akce_prihlaseni_log WHERE id_akce = $1 AND id_uzivatele = $2',
            [$this->aktivita->id(), $ucastnik->id()],
        );

        return (int) $radek['pocet'];
    }

    // === ulozZeDorazil ===

    public function testUlozZeDorazilPredemPrihlaseny()
    {
        $this->prihlasUcastnika($this->ucastnik);

        $zmena = $this->prezence()->ulozZeDorazil($this->ucastnik, $this->vypravec);

        self::assertNotNull($zmena);
        self::assertInstanceOf(ZmenaPrihlaseni::class, $zmena);
        self::assertEquals(AktivitaPrezenceTyp::DORAZIL, $zmena->typPrezence());
        self::assertEquals((int) $this->ucastnik->id(), $zmena->idUzivatele());
        self::assertEquals((int) $this->aktivita->id(), $zmena->idAktivity());
        self::assertGreaterThan(0, $zmena->idLogu());

        self::assertEquals(StavPrihlaseni::PRIHLASEN_A_DORAZIL, $this->dejStavPrihlaseniZDb($this->ucastnik));
        self::assertEquals(AktivitaPrezenceTyp::DORAZIL, $this->dejPosledniLogTyp($this->ucastnik));
    }

    public function testUlozZeDorazilNahradnik()
    {
        // nepřihlášený — přijde jako náhradník
        $zmena = $this->prezence()->ulozZeDorazil($this->ucastnik, $this->vypravec);

        self::assertNotNull($zmena);
        self::assertEquals(AktivitaPrezenceTyp::DORAZIL_JAKO_NAHRADNIK, $zmena->typPrezence());
        self::assertEquals(StavPrihlaseni::DORAZIL_JAKO_NAHRADNIK, $this->dejStavPrihlaseniZDb($this->ucastnik));
        self::assertEquals(AktivitaPrezenceTyp::DORAZIL_JAKO_NAHRADNIK, $this->dejPosledniLogTyp($this->ucastnik));
    }

    public function testUlozZeDorazilUzDorazivsi()
    {
        $this->prihlasUcastnika($this->ucastnik);
        $this->prezence()->ulozZeDorazil($this->ucastnik, $this->vypravec);
        $pocetLoguPred = $this->dejPocetLogu($this->ucastnik);

        // druhé volání — nic se nemění
        $zmena = $this->prezence()->ulozZeDorazil($this->ucastnik, $this->vypravec);

        self::assertNull($zmena);
        self::assertEquals(StavPrihlaseni::PRIHLASEN_A_DORAZIL, $this->dejStavPrihlaseniZDb($this->ucastnik));
        self::assertEquals($pocetLoguPred, $this->dejPocetLogu($this->ucastnik), 'Neměl přibýt žádný nový log');
    }

    public function testUlozZeDorazilIdempotentProNahradnika()
    {
        $this->prezence()->ulozZeDorazil($this->ucastnik, $this->vypravec);
        $pocetLoguPred = $this->dejPocetLogu($this->ucastnik);

        // náhradník dorazil podruhé — nic se nemění
        $this->aktivita->refresh();
        $zmena = $this->prezence()->ulozZeDorazil($this->ucastnik, $this->vypravec);

        self::assertNull($zmena);
        self::assertEquals(StavPrihlaseni::DORAZIL_JAKO_NAHRADNIK, $this->dejStavPrihlaseniZDb($this->ucastnik));
        self::assertEquals($pocetLoguPred, $this->dejPocetLogu($this->ucastnik));
    }

    // === zrusZeDorazil ===

    public function testZrusZeDorazilPredemPrihlaseny()
    {
        $this->prihlasUcastnika($this->ucastnik);
        $this->prezence()->ulozZeDorazil($this->ucastnik, $this->vypravec);
        $this->aktivita->refresh();

        $zmena = $this->prezence()->zrusZeDorazil($this->ucastnik, $this->vypravec);

        self::assertNotNull($zmena);
        self::assertInstanceOf(ZmenaPrihlaseni::class, $zmena);
        self::assertEquals(AktivitaPrezenceTyp::PRIHLASENI, $zmena->typPrezence());
        self::assertEquals(StavPrihlaseni::PRIHLASEN, $this->dejStavPrihlaseniZDb($this->ucastnik));
    }

    public function testZrusZeDorazilNahradnik()
    {
        $this->prezence()->ulozZeDorazil($this->ucastnik, $this->vypravec);
        $this->aktivita->refresh();

        $zmena = $this->prezence()->zrusZeDorazil($this->ucastnik, $this->vypravec);

        self::assertNotNull($zmena);
        self::assertEquals(AktivitaPrezenceTyp::NAHRADNIK_NEDORAZIL, $zmena->typPrezence());
        self::assertNull($this->dejStavPrihlaseniZDb($this->ucastnik), 'Náhradník by měl být odebrán z akce_prihlaseni');
    }

    public function testZrusZeDorazilKdyzNedorazil()
    {
        $this->prihlasUcastnika($this->ucastnik);

        $zmena = $this->prezence()->zrusZeDorazil($this->ucastnik, $this->vypravec);

        self::assertNull($zmena);
        self::assertEquals(StavPrihlaseni::PRIHLASEN, $this->dejStavPrihlaseniZDb($this->ucastnik));
    }

    public function testZrusZeDorazilKdyzNeniPrihlasen()
    {
        $zmena = $this->prezence()->zrusZeDorazil($this->ucastnik, $this->vypravec);

        self::assertNull($zmena);
        self::assertNull($this->dejStavPrihlaseniZDb($this->ucastnik));
    }

    // === ZmenaPrihlaseni consistency ===

    public function testZmenaPrihlaseniMaSpravnyIdLogu()
    {
        $this->prihlasUcastnika($this->ucastnik);

        $zmena = $this->prezence()->ulozZeDorazil($this->ucastnik, $this->vypravec);

        $logZDb = dbOneLine(
            'SELECT id_log FROM akce_prihlaseni_log WHERE id_akce = $1 AND id_uzivatele = $2 ORDER BY id_log DESC LIMIT 1',
            [$this->aktivita->id(), $this->ucastnik->id()],
        );
        self::assertEquals((int) $logZDb['id_log'], $zmena->idLogu());
    }

    public function testZmenaPrihlaseniJeKonzistentniSPosledniZmenou()
    {
        $this->prihlasUcastnika($this->ucastnik);

        $zmenaZUlozeni = $this->prezence()->ulozZeDorazil($this->ucastnik, $this->vypravec);

        $zmenaZDotazu = $this->prezence()->posledniZmenaPrihlaseni($this->ucastnik);

        self::assertNotNull($zmenaZDotazu);
        self::assertEquals($zmenaZUlozeni->idLogu(), $zmenaZDotazu->idLogu());
        self::assertEquals($zmenaZUlozeni->typPrezence(), $zmenaZDotazu->typPrezence());
    }

    // === toggle (check + uncheck) ===

    public function testTogglePredemPrihlaseny()
    {
        $this->prihlasUcastnika($this->ucastnik);

        // check
        $zmenaCheck = $this->prezence()->ulozZeDorazil($this->ucastnik, $this->vypravec);
        self::assertNotNull($zmenaCheck);
        self::assertTrue(StavPrihlaseni::dorazilJakoCokoliv($zmenaCheck->stavPrihlaseni()));

        // uncheck
        $this->aktivita->refresh();
        $zmenaUncheck = $this->prezence()->zrusZeDorazil($this->ucastnik, $this->vypravec);
        self::assertNotNull($zmenaUncheck);
        self::assertFalse(StavPrihlaseni::dorazilJakoCokoliv($zmenaUncheck->stavPrihlaseni()));
        self::assertEquals(StavPrihlaseni::PRIHLASEN, $this->dejStavPrihlaseniZDb($this->ucastnik));

        // re-check
        $this->aktivita->refresh();
        $zmenaRecheck = $this->prezence()->ulozZeDorazil($this->ucastnik, $this->vypravec);
        self::assertNotNull($zmenaRecheck);
        self::assertEquals(StavPrihlaseni::PRIHLASEN_A_DORAZIL, $this->dejStavPrihlaseniZDb($this->ucastnik));
        self::assertGreaterThan($zmenaUncheck->idLogu(), $zmenaRecheck->idLogu());
    }

    public function testToggleNahradnik()
    {
        // check — náhradník
        $zmenaCheck = $this->prezence()->ulozZeDorazil($this->ucastnik, $this->vypravec);
        self::assertNotNull($zmenaCheck);
        self::assertEquals(StavPrihlaseni::DORAZIL_JAKO_NAHRADNIK, $this->dejStavPrihlaseniZDb($this->ucastnik));

        // uncheck — odebrán
        $this->aktivita->refresh();
        $zmenaUncheck = $this->prezence()->zrusZeDorazil($this->ucastnik, $this->vypravec);
        self::assertNotNull($zmenaUncheck);
        self::assertNull($this->dejStavPrihlaseniZDb($this->ucastnik));

        // re-check — opět náhradník
        $this->aktivita->refresh();
        $zmenaRecheck = $this->prezence()->ulozZeDorazil($this->ucastnik, $this->vypravec);
        self::assertNotNull($zmenaRecheck);
        self::assertEquals(StavPrihlaseni::DORAZIL_JAKO_NAHRADNIK, $this->dejStavPrihlaseniZDb($this->ucastnik));
    }

    // === navazující (follow-up) activity in PRIPRAVENA state ===

    public function testUlozZeDorazilNaNavazujiciAktivituVPripravena()
    {
        // Navazující aktivity jsou typicky ve stavu PRIPRAVENA — účastníci
        // se na ně registrují automaticky přes rodičovskou aktivitu.
        // Prezence musí fungovat i bez ručního aktivování.
        dbInsert('akce_seznam', [
            'stav'     => StavAktivity::PRIPRAVENA,
            'typ'      => 1,
            'teamova'  => 0,
            'kapacita' => 10,
            'zacatek'  => '2099-01-01 14:00:00',
            'konec'    => '2099-01-01 18:00:00',
            'rok'      => ROCNIK,
        ]);
        $idNavazujici = dbInsertId();

        $navazujici = Aktivita::zId($idNavazujici, false, $this->systemoveNastaveni);
        self::assertNotNull($navazujici);

        // Ověříme, že zkontrolujZdaSeMuzePrihlasit nevyhodí výjimku
        // při použití flagu STAV (tak jak to dělá OnlinePrezenceAjax)
        $ignorovat = Aktivita::IGNOROVAT_LIMIT | Aktivita::IGNOROVAT_PRIHLASENI_NA_STEJNE_KOLO | Aktivita::STAV;
        $navazujici->zkontrolujZdaSeMuzePrihlasit(
            $this->ucastnik,
            $this->vypravec,
            $ignorovat,
            true,
            true,
        );

        // A prezence samotná funguje
        $zmena = $navazujici->dejPrezenci()->ulozZeDorazil($this->ucastnik, $this->vypravec);
        self::assertNotNull($zmena);
        self::assertEquals(AktivitaPrezenceTyp::DORAZIL_JAKO_NAHRADNIK, $zmena->typPrezence());
    }

    // === multiple users ===

    public function testViceUcastniku()
    {
        $ucastnik2 = self::prihlasenyUzivatel();
        $this->prihlasUcastnika($this->ucastnik);
        $this->prihlasUcastnika($ucastnik2);

        $zmena1 = $this->prezence()->ulozZeDorazil($this->ucastnik, $this->vypravec);
        $this->aktivita->refresh();
        $zmena2 = $this->prezence()->ulozZeDorazil($ucastnik2, $this->vypravec);

        self::assertNotNull($zmena1);
        self::assertNotNull($zmena2);
        self::assertNotEquals($zmena1->idLogu(), $zmena2->idLogu());
        self::assertEquals(StavPrihlaseni::PRIHLASEN_A_DORAZIL, $this->dejStavPrihlaseniZDb($this->ucastnik));
        self::assertEquals(StavPrihlaseni::PRIHLASEN_A_DORAZIL, $this->dejStavPrihlaseniZDb($ucastnik2));
    }
}
