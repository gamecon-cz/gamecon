<?php

declare(strict_types=1);

namespace Gamecon\Tests\Aktivity;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\AktivitaTym;
use Gamecon\Aktivita\SqlStruktura\AkceSeznamSqlStruktura as AktivitaSql;
use Gamecon\Aktivita\StavAktivity;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractUzivatelTestDb;

/**
 * Týmová aktivita s jediným týmem ukazuje obsazená místa pro hráče, ne (0/1) a (1/1) týmů.
 */
class ObsazenostJednohoTymuTest extends AbstractUzivatelTestDb
{
    use ProbihaRegistraceAktivitTrait;

    protected static bool $disableStrictTransTables = true;

    // Tým čte Doctrine na jiném spojení než legacy dbInsert, v transakci by se vzájemně zamkly.
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

    private SystemoveNastaveni $systemoveNastaveni;

    protected function setUp(): void
    {
        parent::setUp();
        $this->systemoveNastaveni = self::vytvorSystemoveNastaveni();
    }

    public function testPrazdnaAktivitaJednohoTymuUkazePocetMistProHrace(): void
    {
        $aktivita = $this->tymovaAktivita(teamKapacita: 1, teamMax: 5);

        self::assertSame(' (0/5)', $aktivita->obsazenostHtml());
    }

    public function testTymDvouLidiUkazeDvaZePetiMist(): void
    {
        $aktivita = $this->tymovaAktivita(teamKapacita: 1, teamMax: 5);
        $this->prihlasHrace($aktivita);
        $this->prihlasHrace($aktivita);

        self::assertSame(' (2/5)', $aktivita->obsazenostHtml());
    }

    public function testPravaStranaJeLimitNastaveniKapitanem(): void
    {
        $aktivita = $this->tymovaAktivita(teamKapacita: 1, teamMax: 5);
        $this->zalozTym($aktivita, limit: 4);
        $this->prihlasHrace($aktivita);
        $this->prihlasHrace($aktivita);

        self::assertSame(' (2/4)', $aktivita->obsazenostHtml());
        self::assertSame(4, $aktivita->obsazenostObj()['ku']);
    }

    public function testTymBezVlastnihoLimituPouzijeKapacituAktivity(): void
    {
        $aktivita = $this->tymovaAktivita(teamKapacita: 1, teamMax: 5);
        $this->zalozTym($aktivita, limit: null);
        $this->prihlasHrace($aktivita);

        self::assertSame(' (1/5)', $aktivita->obsazenostHtml());
        self::assertSame(5, $aktivita->obsazenostObj()['ku']);
    }

    public function testBezLimituKapitanaJePravaStranaTeamMaxNeNahodnaKapacita(): void
    {
        // kapacita v DB se může rozejít s team_max (ruční úprava, import); limitTymu() vždy skládalo team_max
        $aktivita = $this->tymovaAktivita(teamKapacita: 1, teamMax: 5, kapacita: 3);
        $this->zalozTym($aktivita, limit: null);

        self::assertSame(' (0/5)', $aktivita->obsazenostHtml());
        self::assertSame(5, $aktivita->obsazenostObj()['ku']);
    }

    public function testNulovaKapacitaSloupceNeskryjeObsazenost(): void
    {
        $aktivita = $this->tymovaAktivita(teamKapacita: 1, teamMax: 5, kapacita: 0);

        self::assertSame(' (0/5)', $aktivita->obsazenostHtml());
    }

    public function testLimitTymuNadTeamMaxSeOdmitne(): void
    {
        $aktivita = $this->tymovaAktivita(teamKapacita: 1, teamMax: 5);
        $this->zalozTym($aktivita, limit: null);
        $tym = AktivitaTym::vsechnyTymyAktivity($aktivita->id())[0];

        $this->expectException(\Chyba::class);
        $tym->nastavLimit(6);
    }

    public function testAktivitaViceTymuDalePocitaTymy(): void
    {
        $aktivita = $this->tymovaAktivita(teamKapacita: 2, teamMax: 5);
        $this->prihlasHrace($aktivita);

        self::assertSame(' (0/2)', $aktivita->obsazenostHtml());
    }

    public function testApiPosilaTymyIProJedinyTym(): void
    {
        // frontend podle kt pozná jediný tým (kt === 1) a posuzuje ho podle míst pro hráče, ne podle týmů
        $obsazenost = $this->tymovaAktivita(teamKapacita: 1, teamMax: 5, kapacita: 3)->obsazenostObj();

        self::assertSame(1, $obsazenost['kt']);
        self::assertSame(0, $obsazenost['t']);
        self::assertSame(5, $obsazenost['ku']);
    }

    private function tymovaAktivita(int $teamKapacita, int $teamMax, ?int $kapacita = null): Aktivita
    {
        dbInsert(AktivitaSql::AKCE_SEZNAM_TABULKA, [
            AktivitaSql::NAZEV_AKCE    => 'Týmovka',
            AktivitaSql::TYP           => TypAktivity::LARP,
            AktivitaSql::ROK           => ROCNIK,
            AktivitaSql::STAV          => StavAktivity::AKTIVOVANA,
            AktivitaSql::ZACATEK       => ROCNIK . '-07-16 10:00:00',
            AktivitaSql::KONEC         => ROCNIK . '-07-16 13:00:00',
            AktivitaSql::KAPACITA      => $kapacita ?? $teamKapacita * $teamMax,
            AktivitaSql::CENA          => 0,
            AktivitaSql::TEAMOVA       => 1,
            AktivitaSql::TEAM_MIN      => 4,
            AktivitaSql::TEAM_MAX      => $teamMax,
            AktivitaSql::TEAM_KAPACITA => $teamKapacita,
        ]);

        return Aktivita::zId((int) dbInsertId(), false, $this->systemoveNastaveni);
    }

    /**
     * Přímý zápis, protože přihlašování týmových aktivit jde přes tým (viz AktivitaTymovePrihlasovaniTest).
     */
    private function prihlasHrace(Aktivita $aktivita): void
    {
        dbInsert('akce_prihlaseni', [
            'id_akce'             => $aktivita->id(),
            'id_uzivatele'        => self::prihlasenyUzivatel()->id(),
            'id_stavu_prihlaseni' => 0,
        ]);
        $aktivita->otoc();
    }

    private function zalozTym(Aktivita $aktivita, ?int $limit): void
    {
        dbInsert('akce_tym', [
            'kod'        => random_int(100000, 999999),
            'limit'      => $limit,
            'id_kapitan' => self::prihlasenyUzivatel()->id(),
            'zalozen'    => date('Y-m-d H:i:s'),
        ]);
        dbInsert('akce_tym_akce', [
            'id_tymu' => dbInsertId(),
            'id_akce' => $aktivita->id(),
        ]);
    }
}
