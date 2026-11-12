<?php

declare(strict_types=1);

namespace Gamecon\Tests\Kostymy;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Kostymy\ChybaPujcovny;
use Gamecon\Kostymy\NastaveniPujcovny;
use Gamecon\Kostymy\PujcovnaKostymu;
use Gamecon\Kostymy\StavKostymuEnum;
use Gamecon\Kostymy\VysledekRezervaceKostymuEnum;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractUzivatelTestDb;
use PHPUnit\Framework\Attributes\DataProvider;

class PujcovnaKostymuRezervaceTest extends AbstractUzivatelTestDb
{
    use KostymyTestTrait;

    /**
     * Aktivity v testech začínají v 10:00, uzávěrka (2 h) je v 8:00.
     */
    private const string PRED_UZAVERKOU = '2099-01-01 06:00:00';

    protected static bool $disableStrictTransTables = true;

    private function pujcovna(
        string $ted = self::PRED_UZAVERKOU,
        ?NastaveniPujcovny $nastaveni = null,
    ): PujcovnaKostymu {
        return new PujcovnaKostymu(self::systemoveNastaveniKCasu($ted), $nastaveni ?? self::nastaveniPujcovny());
    }

    private static function aktivita(int $idAktivity, string $ted = self::PRED_UZAVERKOU): Aktivita
    {
        $aktivita = Aktivita::zId($idAktivity, false, self::systemoveNastaveniKCasu($ted));
        self::assertNotNull($aktivita);

        return $aktivita;
    }

    /**
     * @return array{0: Aktivita, 1: \Uzivatel}
     */
    private static function ucastnikNaAktivite(
        string $zacatek = '2099-01-01 10:00:00',
        string $konec = '2099-01-01 14:00:00',
        int $typ = TypAktivity::LARP,
    ): array {
        $ucastnik = self::prihlasenyUzivatel();
        $idAktivity = self::vlozAktivitu($zacatek, $konec, $typ);
        self::vlozPrihlasku($idAktivity, $ucastnik->id());

        return [self::aktivita($idAktivity), $ucastnik];
    }

    public function testRezervaceProjde(): void
    {
        [$aktivita, $ucastnik] = self::ucastnikNaAktivite();
        $idKostymu = self::vlozKostym();

        $vysledek = $this->pujcovna()->rezervuj($idKostymu, $aktivita, $ucastnik, $ucastnik);

        self::assertSame(VysledekRezervaceKostymuEnum::NOVA, $vysledek);
        self::assertSame(
            [
                'id_kostymu'  => (string) $idKostymu,
                'rezervoval'  => (string) $ucastnik->id(),
                'rezervovano' => '2099-01-01 06:00:00',
            ],
            dbOneLine('SELECT id_kostymu, rezervoval, rezervovano FROM kostym_rezervace WHERE id_akce = $0', [$aktivita->id()]),
        );
    }

    public function testStejnyKostymZnovuNicNemeni(): void
    {
        [$aktivita, $ucastnik] = self::ucastnikNaAktivite();
        $idKostymu = self::vlozKostym();
        $this->pujcovna()->rezervuj($idKostymu, $aktivita, $ucastnik, $ucastnik);

        self::assertSame(
            VysledekRezervaceKostymuEnum::BEZE_ZMENY,
            $this->pujcovna()->rezervuj($idKostymu, $aktivita, $ucastnik, $ucastnik),
        );
    }

    /**
     * Jiná rezervace kostýmu končí ve 14:00 a výměna trvá 60 minut, kostým je tedy volný od 15:00.
     *
     * @return array<string, array{string, string, bool}>
     */
    public static function zacatkyPoJineRezervaci(): array
    {
        return [
            'od 15:00 projde'                       => ['2099-01-01 15:00:00', '2099-01-01 18:00:00', true],
            'od 14:59 neprojde'                     => ['2099-01-01 14:59:00', '2099-01-01 18:00:00', false],
            'konec v 9:00 projde (výměna do 10:00)' => ['2099-01-01 06:30:00', '2099-01-01 09:00:00', true],
            'konec v 9:01 neprojde'                 => ['2099-01-01 06:30:00', '2099-01-01 09:01:00', false],
        ];
    }

    #[DataProvider('zacatkyPoJineRezervaci')]
    public function testCasNaVymenuMeziRezervacemi(string $zacatek, string $konec, bool $projde): void
    {
        $idKostymu = self::vlozKostym();
        [$jinaAktivita, $jinyUcastnik] = self::ucastnikNaAktivite('2099-01-01 10:00:00', '2099-01-01 14:00:00');
        $this->pujcovna()->rezervuj($idKostymu, $jinaAktivita, $jinyUcastnik, $jinyUcastnik);
        [$aktivita, $ucastnik] = self::ucastnikNaAktivite($zacatek, $konec);

        if (! $projde) {
            $this->expectException(ChybaPujcovny::class);
            $this->expectExceptionMessage('Kostým je v čase aktivity už rezervovaný (počítá se i 60 minut na výměnu');
        }
        $this->pujcovna()->rezervuj($idKostymu, $aktivita, $ucastnik, $ucastnik);
        self::assertCount(1, self::rezervaceAktivity($aktivita->id()));
    }

    public function testVydanyANevracenyKostymBlokujeIBezRezervace(): void
    {
        $idKostymu = self::vlozKostym();
        $jinyUcastnik = self::prihlasenyUzivatel();
        $idJineAktivity = self::vlozAktivitu('2099-01-01 10:00:00', '2099-01-01 14:00:00');
        // rezervace zanikla odhlášením, kostým má účastník pořád u sebe
        self::vlozVydej($idKostymu, null, $idJineAktivity, $jinyUcastnik->id());
        [$aktivita, $ucastnik] = self::ucastnikNaAktivite('2099-01-01 14:30:00', '2099-01-01 18:00:00');

        $this->expectException(ChybaPujcovny::class);
        $this->expectExceptionMessage('už rezervovaný');
        $this->pujcovna()->rezervuj($idKostymu, $aktivita, $ucastnik, $ucastnik);
    }

    public function testVolneKostymyProAktivitu(): void
    {
        $volny = self::vlozKostym('Volný');
        $obsazeny = self::vlozKostym('Obsazený');
        $vOprave = self::vlozKostym('V opravě', StavKostymuEnum::V_OPRAVE);
        $vlastni = self::vlozKostym('Vlastní');
        [$jinaAktivita, $jinyUcastnik] = self::ucastnikNaAktivite('2099-01-01 13:00:00', '2099-01-01 16:00:00');
        $this->pujcovna()->rezervuj($obsazeny, $jinaAktivita, $jinyUcastnik, $jinyUcastnik);
        [$aktivita, $ucastnik] = self::ucastnikNaAktivite();
        $this->pujcovna()->rezervuj($vlastni, $aktivita, $ucastnik, $ucastnik);

        $volne = $this->pujcovna()->volneKostymy($aktivita, $ucastnik);

        self::assertContains($volny, $volne);
        self::assertContains($vlastni, $volne, 'Vlastní rezervace na tutéž aktivitu výměně nepřekáží');
        self::assertNotContains($obsazeny, $volne);
        self::assertNotContains($vOprave, $volne);
        self::assertNotContains($vlastni, $this->pujcovna()->volneKostymy($aktivita), 'Bez účastníka je to cizí rezervace');
    }

    public function testAktivityKRezervaci(): void
    {
        $ucastnik = self::prihlasenyUzivatel();
        $pozdejsiLarp = self::vlozAktivitu('2099-01-02 10:00:00', '2099-01-02 14:00:00');
        $larp = self::vlozAktivitu('2099-01-01 10:00:00', '2099-01-01 14:00:00');
        $rpg = self::vlozAktivitu(typ: TypAktivity::RPG);
        $zacaty = self::vlozAktivitu('2099-01-01 05:00:00', '2099-01-01 08:00:00');
        $sledovany = self::vlozAktivitu();
        foreach ([$pozdejsiLarp, $larp, $rpg, $zacaty] as $idAktivity) {
            self::vlozPrihlasku($idAktivity, $ucastnik->id());
        }
        dbInsert('akce_prihlaseni_spec', [
            'id_akce'             => $sledovany,
            'id_uzivatele'        => $ucastnik->id(),
            'id_stavu_prihlaseni' => StavPrihlaseni::SLEDUJICI,
        ]);

        self::assertSame(
            [$larp, $pozdejsiLarp],
            array_map(static fn (Aktivita $aktivita): int => $aktivita->id(), $this->pujcovna()->aktivityKRezervaci($ucastnik)),
        );
    }

    public function testSledujiciNeprojde(): void
    {
        $ucastnik = self::prihlasenyUzivatel();
        $idAktivity = self::vlozAktivitu();
        dbInsert('akce_prihlaseni_spec', [
            'id_akce'             => $idAktivity,
            'id_uzivatele'        => $ucastnik->id(),
            'id_stavu_prihlaseni' => StavPrihlaseni::SLEDUJICI,
        ]);

        $this->expectException(ChybaPujcovny::class);
        $this->expectExceptionMessage('jen na aktivitu, na kterou máš přihlášku');
        $this->pujcovna()->rezervuj(self::vlozKostym(), self::aktivita($idAktivity), $ucastnik, $ucastnik);
    }

    public function testNahradnikNeprojde(): void
    {
        $ucastnik = self::prihlasenyUzivatel();
        $idAktivity = self::vlozAktivitu();
        self::vlozPrihlasku($idAktivity, $ucastnik->id(), StavPrihlaseni::DORAZIL_JAKO_NAHRADNIK);

        $this->expectException(ChybaPujcovny::class);
        $this->expectExceptionMessage('jen na aktivitu, na kterou máš přihlášku');
        $this->pujcovna()->rezervuj(self::vlozKostym(), self::aktivita($idAktivity), $ucastnik, $ucastnik);
    }

    public function testAktivitaBezCasuNeprojde(): void
    {
        $ucastnik = self::prihlasenyUzivatel();
        $idAktivity = self::vlozAktivitu(null, null);
        self::vlozPrihlasku($idAktivity, $ucastnik->id());

        $this->expectException(ChybaPujcovny::class);
        $this->expectExceptionMessage('Aktivita nemá určený začátek a konec');
        $this->pujcovna()->rezervuj(self::vlozKostym(), self::aktivita($idAktivity), $ucastnik, $ucastnik);
    }

    public function testAktivitaKteraUzZacalaNeprojde(): void
    {
        [$aktivita, $ucastnik] = self::ucastnikNaAktivite();

        $this->expectException(ChybaPujcovny::class);
        $this->expectExceptionMessage('Aktivita už začala');
        $this->pujcovna('2099-01-01 10:00:00')->rezervuj(self::vlozKostym(), $aktivita, $ucastnik, $ucastnik);
    }

    public function testNepovolenyTypAktivityNeprojde(): void
    {
        [$aktivita, $ucastnik] = self::ucastnikNaAktivite(typ: TypAktivity::RPG);

        $this->expectException(ChybaPujcovny::class);
        $this->expectExceptionMessage('se kostýmy nepůjčují');
        $this->pujcovna()->rezervuj(self::vlozKostym(), $aktivita, $ucastnik, $ucastnik);
    }

    public function testTypyAktivitJdeNastavit(): void
    {
        [$aktivita, $ucastnik] = self::ucastnikNaAktivite(typ: TypAktivity::RPG);
        $nastaveni = self::nastaveniPujcovny(typyAktivit: NastaveniPujcovny::parsujTypyAktivit(' 2, 4 ,x'));

        self::assertSame(
            VysledekRezervaceKostymuEnum::NOVA,
            $this->pujcovna(nastaveni: $nastaveni)->rezervuj(self::vlozKostym(), $aktivita, $ucastnik, $ucastnik),
        );
    }

    public function testKostymVOpraveNeprojde(): void
    {
        [$aktivita, $ucastnik] = self::ucastnikNaAktivite();

        $this->expectException(ChybaPujcovny::class);
        $this->expectExceptionMessage('Kostým je v opravě');
        $this->pujcovna()->rezervuj(self::vlozKostym(stav: StavKostymuEnum::V_OPRAVE), $aktivita, $ucastnik, $ucastnik);
    }

    public function testLimitNaOsobuZaRocnik(): void
    {
        $nastaveni = self::nastaveniPujcovny(limitNaOsobu: 1);
        [$prvniAktivita, $ucastnik] = self::ucastnikNaAktivite();
        $idDruheAktivity = self::vlozAktivitu('2099-01-02 10:00:00', '2099-01-02 14:00:00');
        self::vlozPrihlasku($idDruheAktivity, $ucastnik->id());
        $this->pujcovna(nastaveni: $nastaveni)->rezervuj(self::vlozKostym(), $prvniAktivita, $ucastnik, $ucastnik);

        try {
            $this->pujcovna(nastaveni: $nastaveni)->rezervuj(self::vlozKostym(), self::aktivita($idDruheAktivity), $ucastnik, $ucastnik);
            self::fail('Druhá rezervace měla narazit na limit');
        } catch (ChybaPujcovny $chyba) {
            self::assertStringContainsString('nejvýš 1 kostým', $chyba->getMessage());
        }

        $spravce = self::prihlasenyUzivatel();
        self::assertSame(
            VysledekRezervaceKostymuEnum::NOVA,
            $this->pujcovna(nastaveni: $nastaveni)->rezervuj(self::vlozKostym(), self::aktivita($idDruheAktivity), $ucastnik, $spravce, jakoSpravce: true),
        );
    }

    public function testVymenaKostymuPredUzaverkou(): void
    {
        [$aktivita, $ucastnik] = self::ucastnikNaAktivite();
        $puvodni = self::vlozKostym('Plášť');
        $novy = self::vlozKostym('Klobouk');
        $this->pujcovna()->rezervuj($puvodni, $aktivita, $ucastnik, $ucastnik);

        self::assertSame(
            VysledekRezervaceKostymuEnum::VYMENENA,
            $this->pujcovna('2099-01-01 08:00:00')->rezervuj($novy, $aktivita, $ucastnik, $ucastnik),
        );
        self::assertSame((string) $novy, self::rezervaceAktivity($aktivita->id())[0]['id_kostymu']);
    }

    public function testPoUzaverceVymeniAZrusiJenSpravce(): void
    {
        [$aktivita, $ucastnik] = self::ucastnikNaAktivite();
        $puvodni = self::vlozKostym('Plášť');
        $novy = self::vlozKostym('Klobouk');
        $this->pujcovna()->rezervuj($puvodni, $aktivita, $ucastnik, $ucastnik);
        $poUzaverce = $this->pujcovna('2099-01-01 08:01:00');

        foreach ([
            'vyměnit' => fn () => $poUzaverce->rezervuj($novy, $aktivita, $ucastnik, $ucastnik),
            'zrušit'  => fn () => $poUzaverce->zrus($aktivita, $ucastnik),
        ] as $coSeDela => $akce) {
            try {
                $akce();
                self::fail("Po uzávěrce nemělo jít {$coSeDela}");
            } catch (ChybaPujcovny $chyba) {
                self::assertStringContainsString("Kostým jde {$coSeDela} nejpozději 2 h před začátkem", $chyba->getMessage());
            }
        }

        $spravce = self::prihlasenyUzivatel();
        self::assertSame(
            VysledekRezervaceKostymuEnum::VYMENENA,
            $poUzaverce->rezervuj($novy, $aktivita, $ucastnik, $spravce, jakoSpravce: true),
        );
        self::assertSame((string) $novy, $poUzaverce->zrus($aktivita, $ucastnik, jakoSpravce: true)?->getIdKostymu() . '');
        self::assertSame([], self::rezervaceAktivity($aktivita->id()));
    }

    public function testNovouRezervaciUzaverkaNehlida(): void
    {
        [$aktivita, $ucastnik] = self::ucastnikNaAktivite();

        self::assertSame(
            VysledekRezervaceKostymuEnum::NOVA,
            $this->pujcovna('2099-01-01 09:59:00')->rezervuj(self::vlozKostym(), $aktivita, $ucastnik, $ucastnik),
        );
    }

    public function testVydanyKostymUcastnikNezrusi(): void
    {
        [$aktivita, $ucastnik] = self::ucastnikNaAktivite();
        $idKostymu = self::vlozKostym();
        $this->pujcovna()->rezervuj($idKostymu, $aktivita, $ucastnik, $ucastnik);
        $rezervace = $this->pujcovna()->rezervaceUcastnika($aktivita->id(), $ucastnik->id());
        self::vlozVydej($idKostymu, $rezervace->getId(), $aktivita->id(), $ucastnik->id());

        $this->expectException(ChybaPujcovny::class);
        $this->expectExceptionMessage('je už vydaný');
        $this->pujcovna()->zrus($aktivita, $ucastnik);
    }

    public function testSpravceRezervujeZaPrihlasenehoUcastnika(): void
    {
        [$aktivita, $ucastnik] = self::ucastnikNaAktivite();
        $spravce = self::prihlasenyUzivatel();

        $this->pujcovna()->rezervuj(self::vlozKostym(), $aktivita, $ucastnik, $spravce, jakoSpravce: true);

        self::assertSame(
            (string) $spravce->id(),
            dbOneCol('SELECT rezervoval FROM kostym_rezervace WHERE id_akce = $0', [$aktivita->id()]),
        );
    }

    public function testSpravceNerezervujeNeprihlasenemu(): void
    {
        $ucastnik = self::prihlasenyUzivatel();
        $spravce = self::prihlasenyUzivatel();
        $aktivita = self::aktivita(self::vlozAktivitu());

        $this->expectException(ChybaPujcovny::class);
        $this->expectExceptionMessage('Účastník nemá na aktivitu přihlášku');
        $this->pujcovna()->rezervuj(self::vlozKostym(), $aktivita, $ucastnik, $spravce, jakoSpravce: true);
    }

    public function testNezverejnenyKatalogRezervujeJenSpravce(): void
    {
        [$aktivita, $ucastnik] = self::ucastnikNaAktivite();
        $nastaveni = self::nastaveniPujcovny(katalogZverejnen: false);

        try {
            $this->pujcovna(nastaveni: $nastaveni)->rezervuj(self::vlozKostym(), $aktivita, $ucastnik, $ucastnik);
            self::fail('Účastník neměl rezervovat z nezveřejněného katalogu');
        } catch (ChybaPujcovny $chyba) {
            self::assertSame('Půjčovna kostýmů zatím rezervace nepřijímá.', $chyba->getMessage());
        }

        self::assertSame(
            VysledekRezervaceKostymuEnum::NOVA,
            $this->pujcovna(nastaveni: $nastaveni)->rezervuj(self::vlozKostym(), $aktivita, $ucastnik, self::prihlasenyUzivatel(), jakoSpravce: true),
        );
    }

    public function testNastaveniSeCteZeSystemovehoNastaveni(): void
    {
        $nastaveni = NastaveniPujcovny::zSystemovehoNastaveni(SystemoveNastaveni::zGlobals());

        self::assertFalse($nastaveni->isKatalogZverejnen());
        self::assertSame([TypAktivity::LARP], $nastaveni->getTypyAktivit());
        self::assertSame(0, $nastaveni->getLimitNaOsobu());
        self::assertSame(60, $nastaveni->getCasNaVymenuMinut());
        self::assertSame(60, $nastaveni->getVydejMinutPredZacatkem());
        self::assertSame(60, $nastaveni->getVraceniMinutPoKonci());
        self::assertSame(2, $nastaveni->getUzaverkaHodinPredZacatkem());
    }
}
