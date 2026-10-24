<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

use Gamecon\Cas\DateTimeImmutableStrict;
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
    private function vytvorTyp(string $typ): array
    {
        $kodTypu = 'X' . uniqid() . '-' . $typ;
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
    kusu_vyrobeno = 100,
    typ = $4,
    ubytovani_den = $5
SQL,
                [
                    0 => "Postel na {$typ} koleji {$pripona}",
                    1 => "{$kodTypu}_{$pripona}",
                    2 => ROCNIK,
                    3 => StavPredmetu::VEREJNY,
                    4 => TypPredmetu::UBYTOVANI,
                    5 => $noc,
                ],
            );
            $ids[] = dbInsertId();
        }

        return $ids;
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

        // see AbstractTestPrihlaska::jeNabizenKProdeji() why "now" and the template cache are set
        $systemoveNastaveni = SystemoveNastaveni::zGlobals(
            rocnik: ROCNIK,
            ted: new DateTimeImmutableStrict(ROCNIK . '-01-01 00:00:00'),
        );
        $cacheDir = XTemplate::cache() ?: XTPL_CACHE_DIR;
        pripravCache($cacheDir);
        XTemplate::cache($cacheDir);
        $zakladatel = \Uzivatel::zIdUrcite($zakladatel->id());

        $html = (new Shop($zakladatel, $zakladatel, $systemoveNastaveni))->ubytovaniHtml();

        self::assertStringContainsString($kod, $html);
        self::assertStringContainsString(htmlspecialchars((string) $zakladatel->jmenoNick()), $html);
        self::assertStringContainsString('name="shopUbytovaniOpustitSkupinu"', $html);
        self::assertStringNotContainsString('name="shopUbytovaniKodSkupiny"', $html);
    }
}
