<?php

declare(strict_types=1);

namespace Gamecon\Tests\Kostymy;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Kostymy\ChybaPujcovny;
use Gamecon\Kostymy\PrehledyPujcovny;
use Gamecon\Kostymy\StavKostymuEnum;
use Gamecon\Kostymy\StavPriVraceniKostymuEnum;
use Gamecon\Kostymy\VydejKostymu;
use Gamecon\Tests\Db\AbstractUzivatelTestDb;

/**
 * Výdej, vrácení a přehledy správce. Aktivita v testech je 10:00–14:00, výdej od 9:00, vrácení do 15:00.
 */
class PujcovnaKostymuVydejTest extends AbstractUzivatelTestDb
{
    use KostymyTestTrait;

    protected static bool $disableStrictTransTables = true;

    private \Uzivatel $spravce;

    protected function setUp(): void
    {
        parent::setUp();
        $this->spravce = self::prihlasenyUzivatel();
    }

    private static function vydejKostymu(string $ted): VydejKostymu
    {
        return new VydejKostymu(self::systemoveNastaveniKCasu($ted), self::nastaveniPujcovny());
    }

    private static function prehledy(string $ted): PrehledyPujcovny
    {
        return new PrehledyPujcovny(self::systemoveNastaveniKCasu($ted), self::nastaveniPujcovny());
    }

    /**
     * @return array{0: int, 1: int, 2: \Uzivatel, 3: int} ID rezervace, ID kostýmu, účastník, ID aktivity
     */
    private static function rezervace(
        string $zacatek = '2099-01-01 10:00:00',
        string $konec = '2099-01-01 14:00:00',
        ?int $idKostymu = null,
    ): array {
        $ucastnik = self::prihlasenyUzivatel();
        $idAktivity = self::vlozAktivitu($zacatek, $konec);
        self::vlozPrihlasku($idAktivity, $ucastnik->id());
        $idKostymu ??= self::vlozKostym();

        return [self::vlozRezervaci($idKostymu, $idAktivity, $ucastnik->id()), $idKostymu, $ucastnik, $idAktivity];
    }

    public function testVydatJdeNejdrivHodinuPredZacatkem(): void
    {
        [$idRezervace] = self::rezervace();

        try {
            self::vydejKostymu('2099-01-01 08:59:00')->vydej($idRezervace, $this->spravce);
            self::fail('Kostým neměl jít vydat dřív než hodinu před začátkem');
        } catch (ChybaPujcovny $chyba) {
            self::assertStringContainsString('nejdřív 60 minut před začátkem aktivity, tedy od 1. 1. 9:00', $chyba->getMessage());
        }

        $idVydeje = self::vydejKostymu('2099-01-01 09:00:00')->vydej($idRezervace, $this->spravce);
        self::assertSame(
            [
                'id_rezervace' => (string) $idRezervace,
                'vydano'       => '2099-01-01 09:00:00',
                'vydal'        => (string) $this->spravce->id(),
            ],
            dbOneLine('SELECT id_rezervace, vydano, vydal FROM kostym_vydej WHERE id = $0', [$idVydeje]),
        );
    }

    public function testVydanyKostymNejdeVydatZnovu(): void
    {
        [$idRezervace] = self::rezervace();
        self::vydejKostymu('2099-01-01 09:30:00')->vydej($idRezervace, $this->spravce);

        $this->expectException(ChybaPujcovny::class);
        $this->expectExceptionMessage('Kostým už je na tuhle rezervaci vydaný.');
        self::vydejKostymu('2099-01-01 09:31:00')->vydej($idRezervace, $this->spravce);
    }

    public function testPoskozenyKostymJdeDoOpravyAJehoDalsiRezervaceJsouVKonfliktech(): void
    {
        [$idRezervace, $idKostymu] = self::rezervace();
        [$idDalsiRezervace] = self::rezervace('2099-01-02 10:00:00', '2099-01-02 14:00:00', $idKostymu);
        $idVydeje = self::vydejKostymu('2099-01-01 09:30:00')->vydej($idRezervace, $this->spravce);

        self::vydejKostymu('2099-01-01 14:30:00')->vrat($idVydeje, $this->spravce, StavPriVraceniKostymuEnum::POSKOZENY, ' utržený rukáv ');

        self::assertSame(StavKostymuEnum::V_OPRAVE->value, dbOneCol('SELECT stav FROM kostym WHERE id = $0', [$idKostymu]));
        self::assertSame(
            [
                'vraceno'          => '2099-01-01 14:30:00',
                'stav_pri_vraceni' => 'poskozeny',
                'poznamka'         => 'utržený rukáv',
            ],
            dbOneLine('SELECT vraceno, stav_pri_vraceni, poznamka FROM kostym_vydej WHERE id = $0', [$idVydeje]),
        );
        self::assertSame(
            [(string) $idDalsiRezervace],
            array_column(self::prehledy('2099-01-01 14:30:00')->rezervaceKostymuMimoProvoz(), 'id_rezervace'),
        );
    }

    public function testVraceniVPoradkuNechaKostymKDispozici(): void
    {
        [$idRezervace, $idKostymu] = self::rezervace();
        $idVydeje = self::vydejKostymu('2099-01-01 09:30:00')->vydej($idRezervace, $this->spravce);

        self::vydejKostymu('2099-01-01 14:30:00')->vrat($idVydeje, $this->spravce, StavPriVraceniKostymuEnum::V_PORADKU);

        self::assertSame(StavKostymuEnum::K_DISPOZICI->value, dbOneCol('SELECT stav FROM kostym WHERE id = $0', [$idKostymu]));
        $this->expectException(ChybaPujcovny::class);
        $this->expectExceptionMessage('Kostým už je vrácený.');
        self::vydejKostymu('2099-01-01 14:31:00')->vrat($idVydeje, $this->spravce, StavPriVraceniKostymuEnum::V_PORADKU);
    }

    public function testVydanyKostymPoOdhlaseniZustaneMeziNevracenymi(): void
    {
        [$idRezervace, , $ucastnik, $idAktivity] = self::rezervace();
        $idVydeje = self::vydejKostymu('2099-01-01 09:30:00')->vydej($idRezervace, $this->spravce);

        Aktivita::zId($idAktivity, false, self::systemoveNastaveniKCasu('2099-01-01 09:45:00'))
            ->odhlas($ucastnik, $this->spravce, 'test', Aktivita::BEZ_POKUT);

        self::assertSame([], self::rezervaceAktivity($idAktivity));
        self::assertSame([(string) $idVydeje], array_column(self::prehledy('2099-01-01 11:00:00')->nevraceneUcastnika($ucastnik->id()), 'id_vydeje'));
        self::assertSame([], self::prehledy('2099-01-01 15:00:00')->nevracenePoTerminu(), 'Do 15:00 je kostým v termínu');
        $poTerminu = self::prehledy('2099-01-01 15:01:00')->nevracenePoTerminu();
        self::assertSame([(string) $idVydeje], array_column($poTerminu, 'id_vydeje'));
        self::assertSame('2099-01-01 15:00:00', $poTerminu[0]['vratit_do']);
        self::assertNull($poTerminu[0]['id_rezervace']);
    }

    public function testPresunAktivityVytvoriKonflikt(): void
    {
        [$idPrvni, $idKostymu] = self::rezervace('2099-01-01 10:00:00', '2099-01-01 14:00:00');
        [$idDruhe, , , $idDruheAktivity] = self::rezervace('2099-01-01 15:00:00', '2099-01-01 18:00:00', $idKostymu);
        $prehledy = self::prehledy('2099-01-01 06:00:00');
        self::assertSame([], $prehledy->konfliktyRezervaci());

        dbQuery('UPDATE akce_seznam SET zacatek = $0 WHERE id_akce = $1', ['2099-01-01 14:30:00', $idDruheAktivity]);

        $konflikty = $prehledy->konfliktyRezervaci();
        self::assertCount(1, $konflikty);
        self::assertSame([(string) $idPrvni, (string) $idDruhe], [$konflikty[0]['prvni']['id_rezervace'], $konflikty[0]['druha']['id_rezervace']]);
        self::assertSame([], self::prehledy('2099-01-01 18:00:00')->konfliktyRezervaci(), 'Proběhlé konflikty se už neukazují');
    }

    public function testHledaJenUcastnikyPujcovny(): void
    {
        [, , $ucastnik] = self::rezervace();
        $nikdo = self::prihlasenyUzivatel();
        $prehledy = self::prehledy('2099-01-01 06:00:00');

        // číslo se hledá i v e-mailech a ty testovací obsahují náhodná čísla, proto Contains, ne přesný výsledek
        self::assertContains($ucastnik->id(), $prehledy->hledejUcastniky((string) $ucastnik->id()));
        self::assertSame([$ucastnik->id()], $prehledy->hledejUcastniky($ucastnik->mail()));
        self::assertNotContains($nikdo->id(), $prehledy->hledejUcastniky((string) $nikdo->id()), 'Kdo nemá larp ani kostým, toho výdej nenajde');
        self::assertNotContains($nikdo->id(), $prehledy->hledejUcastniky($nikdo->mail()));
        self::assertSame([], $prehledy->hledejUcastniky('%'), 'Procento se hledá doslova');
    }

    public function testVydejniListUkazeAktivityVPristichHodinach(): void
    {
        [$idBrzke] = self::rezervace('2099-01-01 10:00:00', '2099-01-01 14:00:00');
        [$idPozdni] = self::rezervace('2099-01-01 16:00:00', '2099-01-01 18:00:00');

        self::assertSame([(string) $idBrzke], array_column(self::prehledy('2099-01-01 08:00:00')->vydejniList(4), 'id_rezervace'));
        self::assertSame(
            [(string) $idBrzke, (string) $idPozdni],
            array_column(self::prehledy('2099-01-01 13:00:00')->vydejniList(4), 'id_rezervace'),
            'Probíhající aktivita na listu zůstane, dokud neskončí',
        );
    }
}
