<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

use Gamecon\Shop\PodtypPredmetu;
use Gamecon\Shop\Shop;
use Gamecon\Shop\StavPredmetu;
use Gamecon\Shop\TypPredmetu;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Ubytovani\UbytovaciSkupiny;
use Gamecon\XTemplate\XTemplate;

/**
 * Roommate group in the public registration form: founding, joining by code, a wrong code,
 * leaving the group when the accommodation no longer fits and when leaving GameCon.
 */
class PrihlaskaSkupinaSpolubydlicichTest extends AbstractTestPrihlaska
{
    private const string COOKIE_VAROVANI = 'CHYBY_CLASS_VAROVANI';
    private const string COOKIE_OZNAMENI = 'CHYBY_CLASS_OZNAMENI';

    protected function setUp(): void
    {
        parent::setUp();
        unset($_COOKIE[self::COOKIE_VAROVANI], $_COOKIE[self::COOKIE_OZNAMENI]);
    }

    protected function tearDown(): void
    {
        unset($_COOKIE[self::COOKIE_VAROVANI], $_COOKIE[self::COOKIE_OZNAMENI]);
        parent::tearDown();
    }

    /**
     * Two nights of one type, coded like the real items ("2L_ct"), so that both nights are one type.
     *
     * @return int[] item ids
     */
    private function vytvorTyp(string $typ, int $kusuVyrobeno = 100, ?string $podtyp = null): array
    {
        $kodTypu = 'X' . uniqid() . '-' . $typ;
        $budova = $podtyp === PodtypPredmetu::HOTEL
            ? 'hotelu'
            : 'koleji';
        $ids = [];
        foreach ([
            self::DEN_CTVRTEK => 'ct',
            self::DEN_PATEK   => 'pa',
        ] as $noc => $pripona) {
            dbQuery(<<<'SQL'
INSERT INTO shop_predmety SET
    nazev = $0,
    kod_predmetu = $1,
    model_rok = $2,
    cena_aktualni = 500,
    stav = $3,
    kusu_vyrobeno = $4,
    typ = $5,
    podtyp = $6,
    ubytovani_den = $7
SQL,
                [
                    0 => "Postel na {$typ} {$budova} {$pripona}",
                    1 => "{$kodTypu}_{$pripona}",
                    2 => ROCNIK,
                    3 => StavPredmetu::VEREJNY,
                    4 => $kusuVyrobeno,
                    5 => TypPredmetu::UBYTOVANI,
                    6 => $podtyp,
                    7 => $noc,
                ],
            );
            $ids[] = dbInsertId();
        }

        return $ids;
    }

    /**
     * @return int[] item ids of two nights on a hotel with as many beds
     */
    private function vytvorHotel(int $posteli): array
    {
        return $this->vytvorTyp('2L', $posteli, PodtypPredmetu::HOTEL);
    }

    private function kodSkupiny(\Uzivatel $uzivatel): ?string
    {
        return (new UbytovaciSkupiny(ROCNIK))->kodSkupiny((int) $uzivatel->id());
    }

    /**
     * @return string[]
     */
    private function zpravy(string $cookie): array
    {
        return json_decode($_COOKIE[$cookie] ?? '[]', true) ?: [];
    }

    /**
     * @test
     */
    public function jedenSkupinuZalozilADruhySeKNiPripojilKodem(): void
    {
        $typ = $this->vytvorTyp('2L');
        $zakladatel = $this->vytvorBeznehoUzivatele();
        $kamarad = $this->vytvorBeznehoUzivatele();

        $this->odesliPrihlasku($zakladatel, [
            'shopUbytovaniDny'            => $typ,
            'shopUbytovaniZalozitSkupinu' => '1',
        ]);
        $kod = $this->kodSkupiny($zakladatel);
        self::assertNotNull($kod, 'Zaškrtnutí „založit novou skupinu“ musí skupinu založit');

        $this->odesliPrihlasku($kamarad, [
            'shopUbytovaniDny'        => $typ,
            'shopUbytovaniKodSkupiny' => $kod,
        ]);

        self::assertSame($kod, $this->kodSkupiny($kamarad));
        self::assertSame([], $this->zpravy(self::COOKIE_VAROVANI));
    }

    /**
     * @test
     */
    public function chybnyKodNezahodiZbytekPrihlasky(): void
    {
        $typ = $this->vytvorTyp('2L');
        $uzivatel = $this->vytvorBeznehoUzivatele();

        $this->odesliPrihlasku($uzivatel, [
            'shopUbytovaniDny'        => $typ,
            'shopUbytovaniKodSkupiny' => 'NENIKOD',
        ]);

        foreach ($typ as $idNoci) {
            self::assertSame(1, $this->pocetNakupu($uzivatel, $idNoci), 'Ubytování se musí uložit i s chybným kódem skupiny');
        }
        self::assertNull($this->kodSkupiny($uzivatel));
        self::assertCount(1, $this->zpravy(self::COOKIE_VAROVANI));
        self::assertStringContainsString('NENIKOD', $this->zpravy(self::COOKIE_VAROVANI)[0]);
    }

    /**
     * @test
     */
    public function zmenaTypuUbytovaniVPrihlasceOpustiSkupinu(): void
    {
        $dvojluzko = $this->vytvorTyp('2L');
        $trojluzko = $this->vytvorTyp('3L');
        $zakladatel = $this->vytvorBeznehoUzivatele();
        $kamarad = $this->vytvorBeznehoUzivatele();
        $this->odesliPrihlasku($zakladatel, [
            'shopUbytovaniDny'            => $dvojluzko,
            'shopUbytovaniZalozitSkupinu' => '1',
        ]);
        $kod = $this->kodSkupiny($zakladatel);
        $this->odesliPrihlasku($kamarad, [
            'shopUbytovaniDny'        => $dvojluzko,
            'shopUbytovaniKodSkupiny' => $kod,
        ]);

        $this->odesliPrihlasku($kamarad, [
            'shopUbytovaniDny' => $trojluzko,
        ]);

        self::assertNull($this->kodSkupiny($kamarad));
        self::assertSame($kod, $this->kodSkupiny($zakladatel));
        self::assertStringContainsString($kod, implode(' ', $this->zpravy(self::COOKIE_VAROVANI)));
    }

    /**
     * @test
     */
    public function opusteniSkupinyPresPrihlasku(): void
    {
        $uzivatel = $this->vytvorBeznehoUzivatele();
        $typ = $this->vytvorTyp('3L');
        $this->odesliPrihlasku($uzivatel, [
            'shopUbytovaniDny'            => $typ,
            'shopUbytovaniZalozitSkupinu' => '1',
        ]);
        self::assertNotNull($this->kodSkupiny($uzivatel));

        $this->odesliPrihlasku($uzivatel, [
            'shopUbytovaniDny'            => $typ,
            'shopUbytovaniOpustitSkupinu' => '1',
        ]);

        self::assertNull($this->kodSkupiny($uzivatel));
    }

    /**
     * @test
     */
    public function odhlaseniZGameconuOpustiSkupinu(): void
    {
        $uzivatel = $this->vytvorBeznehoUzivatele();
        $this->odesliPrihlasku($uzivatel, [
            'shopUbytovaniDny'            => $this->vytvorTyp('2L'),
            'shopUbytovaniZalozitSkupinu' => '1',
        ]);
        self::assertNotNull($this->kodSkupiny($uzivatel));

        $uzivatel = \Uzivatel::zIdUrcite($uzivatel->id());
        $uzivatel->odhlasZGc('test', $uzivatel, odeslatMailPokudSeNeodhlasilSam: false);

        self::assertNull($this->kodSkupiny($uzivatel));
    }

    /**
     * @test
     */
    public function formularUkazeKodSkupinyAJejiCleny(): void
    {
        $typ = $this->vytvorTyp('2L');
        $zakladatel = $this->vytvorBeznehoUzivatele();
        $this->odesliPrihlasku($zakladatel, [
            'shopUbytovaniDny'            => $typ,
            'shopUbytovaniZalozitSkupinu' => '1',
        ]);
        $kod = $this->kodSkupiny($zakladatel);
        $zakladatel = \Uzivatel::zIdUrcite($zakladatel->id());

        $html = $this->vykresliUbytovani($zakladatel);

        self::assertStringContainsString($kod, $html);
        self::assertStringContainsString(htmlspecialchars((string) $zakladatel->jmenoNick()), $html);
        self::assertStringContainsString('name="shopUbytovaniOpustitSkupinu"', $html);
        self::assertStringNotContainsString('name="shopUbytovaniKodSkupiny"', $html);
    }

    /**
     * @test
     */
    public function naHoteluDrziSkupinaZbylePostelePokojeProTyKdoSePripojiKodem(): void
    {
        $hotel = $this->vytvorHotel(posteli: 2);
        $zakladatel = $this->vytvorBeznehoUzivatele();
        $cizi = $this->vytvorBeznehoUzivatele();
        $kamarad = $this->vytvorBeznehoUzivatele();
        $this->odesliPrihlasku($zakladatel, [
            'shopUbytovaniDny'            => $hotel,
            'shopUbytovaniZalozitSkupinu' => '1',
        ]);
        $kod = $this->kodSkupiny($zakladatel);

        $chyba = $this->odesliPrihlaskuAZachytChybu($cizi, [
            'shopUbytovaniDny' => $hotel,
        ]);
        self::assertNotNull($chyba, 'Druhou postel pokoje drží skupina, bez jejího kódu ji nikdo dostat nesmí');
        self::assertStringContainsString('drží skupiny spolubydlících', $chyba->getMessage());
        self::assertSame(0, $this->pocetNakupu($cizi, $hotel[0]));

        $this->odesliPrihlasku($kamarad, [
            'shopUbytovaniDny'        => $hotel,
            'shopUbytovaniKodSkupiny' => $kod,
        ]);
        self::assertSame($kod, $this->kodSkupiny($kamarad));
        self::assertSame(1, $this->pocetNakupu($kamarad, $hotel[0]));
    }

    /**
     * @test
     */
    public function sChybnymKodemNaPlnemHoteluChybaVysvetliProc(): void
    {
        $hotel = $this->vytvorHotel(posteli: 2);
        $zakladatel = $this->vytvorBeznehoUzivatele();
        $kamarad = $this->vytvorBeznehoUzivatele();
        $this->odesliPrihlasku($zakladatel, [
            'shopUbytovaniDny'            => $hotel,
            'shopUbytovaniZalozitSkupinu' => '1',
        ]);

        $chyba = $this->odesliPrihlaskuAZachytChybu($kamarad, [
            'shopUbytovaniDny'        => $hotel,
            'shopUbytovaniKodSkupiny' => 'NENIKOD',
        ]);

        self::assertNotNull($chyba);
        self::assertStringContainsString('NENIKOD', $chyba->getMessage());
        self::assertSame(0, $this->pocetNakupu($kamarad, $hotel[0]));
        self::assertSame([], $this->zpravy(self::COOKIE_VAROVANI), 'Varování skupiny patří do chyby, přihláška se neuložila');
    }

    /**
     * @test
     */
    public function naHoteluNejdeZalozitViceSkupinNezJePokoju(): void
    {
        $hotel = $this->vytvorHotel(posteli: 4);
        $prvni = $this->vytvorBeznehoUzivatele();
        $druhy = $this->vytvorBeznehoUzivatele();
        $treti = $this->vytvorBeznehoUzivatele();
        foreach ([$prvni, $druhy] as $zakladatel) {
            $this->odesliPrihlasku($zakladatel, [
                'shopUbytovaniDny'            => $hotel,
                'shopUbytovaniZalozitSkupinu' => '1',
            ]);
        }

        $chyba = $this->odesliPrihlaskuAZachytChybu($treti, [
            'shopUbytovaniDny'            => $hotel,
            'shopUbytovaniZalozitSkupinu' => '1',
        ]);

        self::assertNotNull($chyba, 'Dva pokoje mají už dvě skupiny, třetí se nevejde');
        self::assertStringContainsString('skupinu spolubydlících proto založit nejde', $chyba->getMessage());
        self::assertNull($this->kodSkupiny($treti));
        self::assertSame(0, $this->pocetNakupu($treti, $hotel[0]));
    }

    /**
     * @test
     */
    public function jednotlivciBezSkupinySeNaHoteluVejdouDoZbylychPosteli(): void
    {
        $hotel = $this->vytvorHotel(posteli: 4);
        $this->odesliPrihlasku($this->vytvorBeznehoUzivatele(), [
            'shopUbytovaniDny'            => $hotel,
            'shopUbytovaniZalozitSkupinu' => '1',
        ]);
        $jednotlivec = $this->vytvorBeznehoUzivatele();
        $this->odesliPrihlasku($jednotlivec, [
            'shopUbytovaniDny' => $hotel,
        ]);

        $chyba = $this->odesliPrihlaskuAZachytChybu($this->vytvorBeznehoUzivatele(), [
            'shopUbytovaniDny'            => $hotel,
            'shopUbytovaniZalozitSkupinu' => '1',
        ]);
        self::assertNotNull($chyba, 'Na druhém pokoji už spí jednotlivec, celý pokoj pro skupinu nezbyl');

        $druhyJednotlivec = $this->vytvorBeznehoUzivatele();
        $this->odesliPrihlasku($druhyJednotlivec, [
            'shopUbytovaniDny' => $hotel,
        ]);
        self::assertSame(1, $this->pocetNakupu($druhyJednotlivec, $hotel[0]), 'Jednotlivci se vejdou na jeden pokoj');
    }

    /**
     * @test
     */
    public function zeSkupinyNaPlnemHoteluNejdeOdejitAUbytovaniSiNechat(): void
    {
        $hotel = $this->vytvorHotel(posteli: 2);
        $zakladatel = $this->vytvorBeznehoUzivatele();
        $kamarad = $this->vytvorBeznehoUzivatele();
        $this->odesliPrihlasku($zakladatel, [
            'shopUbytovaniDny'            => $hotel,
            'shopUbytovaniZalozitSkupinu' => '1',
        ]);
        $kod = $this->kodSkupiny($zakladatel);
        $this->odesliPrihlasku($kamarad, [
            'shopUbytovaniDny'        => $hotel,
            'shopUbytovaniKodSkupiny' => $kod,
        ]);

        $chyba = $this->odesliPrihlaskuAZachytChybu($kamarad, [
            'shopUbytovaniDny'            => $hotel,
            'shopUbytovaniOpustitSkupinu' => '1',
        ]);

        self::assertNotNull($chyba, 'Skupina by dál držela postel a odcházející by potřeboval další');
        self::assertStringContainsString('odejít a ubytování si nechat nejde', $chyba->getMessage());
        self::assertSame($kod, $this->kodSkupiny($kamarad));
    }

    /**
     * @test
     */
    public function preplnenyHotelNebraniUlozitPrihlaskuBezZmenyUbytovani(): void
    {
        $hotel = $this->vytvorHotel(posteli: 2);
        $zakladatel = $this->vytvorBeznehoUzivatele();
        $this->odesliPrihlasku($zakladatel, [
            'shopUbytovaniDny'            => $hotel,
            'shopUbytovaniZalozitSkupinu' => '1',
        ]);
        // přes kapacitu v adminu
        $navic = $this->vytvorBeznehoUzivatele();
        foreach ($hotel as $idNoci) {
            dbQuery(<<<'SQL'
INSERT INTO shop_nakupy SET id_uzivatele = $0, id_predmetu = $1, rok = $2, cena_nakupni = 500, datum = NOW()
SQL,
                [
                    0 => $navic->id(),
                    1 => $idNoci,
                    2 => ROCNIK,
                ],
            );
        }

        $chyba = $this->odesliPrihlaskuAZachytChybu($zakladatel, [
            'shopUbytovaniDny' => $hotel,
        ]);

        self::assertNull($chyba, 'Přeplnění, které přihláška nezvětšila, ji nesmí zablokovat');
    }

    /**
     * @test
     */
    public function naKolejiSkupinaPosteleNedrzi(): void
    {
        $kolej = $this->vytvorTyp('2L', kusuVyrobeno: 2);
        $this->odesliPrihlasku($this->vytvorBeznehoUzivatele(), [
            'shopUbytovaniDny'            => $kolej,
            'shopUbytovaniZalozitSkupinu' => '1',
        ]);
        $jednotlivec = $this->vytvorBeznehoUzivatele();

        $this->odesliPrihlasku($jednotlivec, [
            'shopUbytovaniDny' => $kolej,
        ]);

        self::assertSame(1, $this->pocetNakupu($jednotlivec, $kolej[0]));
    }

    /**
     * @test
     */
    public function postelDrzenouSkupinouFormularOdemkneAzSKodem(): void
    {
        $hotel = $this->vytvorHotel(posteli: 2);
        $zakladatel = $this->vytvorBeznehoUzivatele();
        $this->odesliPrihlasku($zakladatel, [
            'shopUbytovaniDny'            => $hotel,
            'shopUbytovaniZalozitSkupinu' => '1',
        ]);

        $html = $this->vykresliUbytovani($this->vytvorBeznehoUzivatele());

        self::assertMatchesRegularExpression(
            '~value="' . $hotel[0] . '"[^>]*data-jen-s-kodem-skupiny="1"[^>]*disabled>\s*<div class="shopUbytovani_kapacita">2/2</div>~',
            $html,
            'Postel držená skupinou se počítá jako obsazená a bez kódu nejde vybrat',
        );
        self::assertStringContainsString('shopUbytovani_drzenePostele', $html);

        $htmlZakladatele = $this->vykresliUbytovani($zakladatel);
        self::assertStringNotContainsString('data-jen-s-kodem-skupiny="1"', $htmlZakladatele);

        self::assertMatchesRegularExpression(
            '~value="' . $hotel[0] . '"[^>]*>\s*<div class="shopUbytovani_kapacita">1/2</div>~',
            $this->vykresliUbytovani($this->vytvorBeznehoUzivatele(), poKonciProdeje: true),
            'Po konci prodeje se nikdo kódem nepřipojí, infopult smí na nepoužitou postel dát kohokoli',
        );
    }

    /**
     * @test
     */
    public function kdoPrijedeSamSeZaskrtnutimZapiseNaPrideleniSpolubydlicich(): void
    {
        $typ = $this->vytvorTyp('2L');
        $uzivatel = $this->vytvorBeznehoUzivatele();
        $skupiny = new UbytovaciSkupiny(ROCNIK);

        $this->odesliPrihlasku($uzivatel, [
            'shopUbytovaniDny'     => $typ,
            'shopUbytovaniSamotar' => '1',
        ]);
        self::assertTrue($skupiny->jeSamotar((int) $uzivatel->id()));
        self::assertMatchesRegularExpression(
            '~name="shopUbytovaniSamotar" value="1" checked~',
            $this->vykresliUbytovani($uzivatel),
        );

        $this->odesliPrihlasku($uzivatel, [
            'shopUbytovaniDny' => $typ,
        ]);
        self::assertFalse($skupiny->jeSamotar((int) $uzivatel->id()), 'Odškrtnutím na spolubydlící přestane čekat');
    }

    /**
     * @test
     */
    public function zalozeniSkupinyMaPrednostPredPrijezdemSamotare(): void
    {
        $uzivatel = $this->vytvorBeznehoUzivatele();

        $this->odesliPrihlasku($uzivatel, [
            'shopUbytovaniDny'            => $this->vytvorTyp('2L'),
            'shopUbytovaniZalozitSkupinu' => '1',
            'shopUbytovaniSamotar'        => '1',
        ]);

        self::assertNotNull($this->kodSkupiny($uzivatel));
        self::assertFalse((new UbytovaciSkupiny(ROCNIK))->jeSamotar((int) $uzivatel->id()));
    }

    /**
     * @test
     */
    public function zpravaOZalozeniSkupinyNezustaneKdyzSePrihlaskaVrati(): void
    {
        $uzivatel = $this->vytvorBeznehoUzivatele();
        $puvodniPost = $_POST;
        $_POST = [
            'shopUbytovaniDny'            => $this->vytvorTyp('2L'),
            'shopUbytovaniZalozitSkupinu' => '1',
        ];
        try {
            $uzivatel = \Uzivatel::zIdUrcite($uzivatel->id());
            $shop = new Shop($uzivatel, $uzivatel, SystemoveNastaveni::zGlobals());
            dbBegin();
            $shop->zpracujUbytovani(ulozitNechceUbytovani: true);
            // a later part of the registration fails
            dbRollback();
        } finally {
            $_POST = $puvodniPost;
            \Uzivatel::smazCache();
        }

        self::assertNull($this->kodSkupiny($uzivatel));
        self::assertSame([], $this->zpravy(self::COOKIE_OZNAMENI), 'Hláška o založené skupině patří až za uložení přihlášky');
    }

    /**
     * @test
     */
    public function nocKdyNaHoteluNezbyvaCelyPokojFormularProSkupinuZamkne(): void
    {
        // a room has two beds and the hotel three per night
        [$ctvrtek, $patek] = $this->vytvorTyp('2L', kusuVyrobeno: 3, podtyp: PodtypPredmetu::HOTEL);
        $zakladatel = $this->vytvorBeznehoUzivatele();
        $this->nakup($zakladatel, $ctvrtek);
        (new UbytovaciSkupiny(ROCNIK))->zaloz(\Uzivatel::zIdUrcite($zakladatel->id()));
        // on Friday two people without a group leave one bed
        $this->nakup($this->vytvorBeznehoUzivatele(), $patek);
        $this->nakup($this->vytvorBeznehoUzivatele(), $patek);

        self::assertMatchesRegularExpression(
            '~value="' . $patek . '"[^>]*disabled>~',
            $this->vykresliUbytovani($zakladatel),
            'V pátek skupina na hotelu nikoho nemá, přidat ho by chtělo celý pokoj',
        );

        $html = $this->vykresliUbytovani($this->vytvorBeznehoUzivatele());
        self::assertMatchesRegularExpression(
            '~value="' . $patek . '"[^>]*data-malo-na-pokoj="1"\s*>~',
            $html,
            'Postel bez skupiny objednat jde, skupinu s ní formulář založit nedovolí',
        );
        self::assertStringContainsString('shopUbytovani_zalozeniBezPokoje', $html);
    }

    private function nakup(\Uzivatel $uzivatel, int $idPredmetu): void
    {
        dbQuery(<<<'SQL'
INSERT INTO shop_nakupy SET id_uzivatele = $0, id_predmetu = $1, rok = $2, cena_nakupni = 500, datum = NOW()
SQL,
            [
                0 => $uzivatel->id(),
                1 => $idPredmetu,
                2 => ROCNIK,
            ],
        );
    }

    private function vykresliUbytovani(\Uzivatel $uzivatel, bool $poKonciProdeje = false): string
    {
        // The end of accommodation sales is a constant another test may define in the same process,
        // so "now" is set a day before or after it, whatever its date.
        $konecProdeje = SystemoveNastaveni::zGlobals(rocnik: ROCNIK)->prodejUbytovaniDo();
        $systemoveNastaveni = SystemoveNastaveni::zGlobals(
            rocnik: ROCNIK,
            ted: $konecProdeje->modify($poKonciProdeje
                ? '+1 day'
                : '-1 day'),
        );
        // see AbstractTestPrihlaska::jeNabizenKProdeji() why the template cache is set
        $cacheDir = XTemplate::cache() ?: XTPL_CACHE_DIR;
        pripravCache($cacheDir);
        XTemplate::cache($cacheDir);
        $uzivatel = \Uzivatel::zIdUrcite($uzivatel->id());

        return (new Shop($uzivatel, $uzivatel, $systemoveNastaveni))->ubytovaniHtml(muzeEditovatUkoncenyProdej: true);
    }
}
