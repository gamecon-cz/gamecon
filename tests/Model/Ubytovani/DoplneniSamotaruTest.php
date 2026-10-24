<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Ubytovani;

use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Exceptions\ChybaUbytovaciSkupiny;
use Gamecon\Shop\PodtypPredmetu;
use Gamecon\Shop\StavPredmetu;
use Gamecon\Shop\TypPredmetu;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Ubytovani\DoplneniSamotaru;
use Gamecon\Ubytovani\UbytovaciSkupiny;
use Gamecon\Uzivatel\Pohlavi;

class DoplneniSamotaruTest extends AbstractTestDb
{
    private const int CTVRTEK = DateTimeGamecon::PORADI_HERNIHO_DNE_CTVRTEK;
    private const int PATEK = DateTimeGamecon::PORADI_HERNIHO_DNE_PATEK;
    private const int SOBOTA = DateTimeGamecon::PORADI_HERNIHO_DNE_SOBOTA;

    protected static function keepTestClassDbChangesInTransaction(): bool
    {
        return true;
    }

    private function skupiny(): UbytovaciSkupiny
    {
        return new UbytovaciSkupiny(ROCNIK);
    }

    private function vytvorUzivatele(string $pohlavi): \Uzivatel
    {
        $unikat = uniqid();
        dbQuery(<<<'SQL'
INSERT INTO uzivatele_hodnoty SET
    login_uzivatele = $0,
    email1_uzivatele = $1,
    jmeno_uzivatele = 'Samotář',
    prijmeni_uzivatele = 'Testovací',
    pohlavi = $2
SQL,
            [
                0 => 'test_samotar_' . $unikat,
                1 => 'test.samotar.' . $unikat . '@example.org',
                2 => $pohlavi,
            ],
        );

        return \Uzivatel::zIdUrcite(dbInsertId());
    }

    /**
     * Thursday to Saturday night of one type, coded like the real ones ("2L_ct").
     *
     * @return array<int, int> item id by night
     */
    private function vytvorTyp(string $typ, int $posteli = 100, ?string $podtyp = null): array
    {
        $kodTypu = 'X' . uniqid() . '-' . $typ;
        $ids = [];
        foreach ([
            self::CTVRTEK => ['ct', 'čtvrtek'],
            self::PATEK   => ['pa', 'pátek'],
            self::SOBOTA  => ['so', 'sobota'],
        ] as $noc => [$pripona, $den]) {
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
                    0 => "Postel na {$typ} {$den}",
                    1 => "{$kodTypu}_{$pripona}",
                    2 => ROCNIK,
                    3 => StavPredmetu::VEREJNY,
                    4 => $posteli,
                    5 => TypPredmetu::UBYTOVANI,
                    6 => $podtyp,
                    7 => $noc,
                ],
            );
            $ids[$noc] = dbInsertId();
        }

        return $ids;
    }

    /**
     * @param array<int, int> $typ  item id by night
     * @param int[]           $noci
     */
    private function objednej(\Uzivatel $uzivatel, array $typ, array $noci): void
    {
        foreach ($noci as $noc) {
            dbQuery(<<<'SQL'
INSERT INTO shop_nakupy SET id_uzivatele = $0, id_predmetu = $1, rok = $2, cena_nakupni = 500, datum = NOW()
SQL,
                [
                    0 => $uzivatel->id(),
                    1 => $typ[$noc],
                    2 => ROCNIK,
                ],
            );
        }
    }

    /**
     * @param array<int, int> $typ
     * @param int[]           $noci
     */
    private function samotar(string $pohlavi, array $typ, array $noci): \Uzivatel
    {
        $samotar = $this->vytvorUzivatele($pohlavi);
        $this->objednej($samotar, $typ, $noci);
        $this->skupiny()->prihlasSamotare($samotar);

        return $samotar;
    }

    private function kod(\Uzivatel $uzivatel): ?string
    {
        return $this->skupiny()->kodSkupiny((int) $uzivatel->id());
    }

    private function dopln(): array
    {
        return (new DoplneniSamotaru(ROCNIK))->dopln();
    }

    /**
     * @test
     */
    public function samotarDostaneVolnouPostelVeSkupineSeStejnymPohlavim(): void
    {
        $kolej = $this->vytvorTyp('2L');
        $zakladatel = $this->vytvorUzivatele(Pohlavi::MUZ_KOD);
        $this->objednej($zakladatel, $kolej, [self::CTVRTEK, self::PATEK, self::SOBOTA]);
        $kod = $this->skupiny()->zaloz($zakladatel);
        $samotar = $this->samotar(Pohlavi::MUZ_KOD, $kolej, [self::PATEK, self::SOBOTA]);

        $vysledek = $this->dopln();

        self::assertSame($kod, $this->kod($samotar));
        self::assertFalse($this->skupiny()->jeSamotar((int) $samotar->id()), 'Ve skupině už na spolubydlící nečeká');
        self::assertSame(1, $vysledek['doSkupin']);
    }

    /**
     * @test
     */
    public function doSkupinyJinehoPohlaviSamotareNedoplni(): void
    {
        $kolej = $this->vytvorTyp('2L');
        $zakladatelka = $this->vytvorUzivatele(Pohlavi::ZENA_KOD);
        $this->objednej($zakladatelka, $kolej, [self::CTVRTEK, self::PATEK]);
        $this->skupiny()->zaloz($zakladatelka);
        $samotar = $this->samotar(Pohlavi::MUZ_KOD, $kolej, [self::CTVRTEK, self::PATEK]);

        $this->dopln();

        self::assertNull($this->kod($samotar));
        self::assertTrue($this->skupiny()->jeSamotar((int) $samotar->id()), 'Kdo se nikam nevešel, čeká dál');
    }

    /**
     * @test
     */
    public function doSkupinySamotareNedoplniNaNocKdyVNiNikdoNespi(): void
    {
        $kolej = $this->vytvorTyp('2L');
        $zakladatel = $this->vytvorUzivatele(Pohlavi::MUZ_KOD);
        $this->objednej($zakladatel, $kolej, [self::CTVRTEK, self::PATEK]);
        $this->skupiny()->zaloz($zakladatel);
        $samotar = $this->samotar(Pohlavi::MUZ_KOD, $kolej, [self::CTVRTEK, self::PATEK, self::SOBOTA]);

        $this->dopln();

        self::assertNull($this->kod($samotar));
    }

    /**
     * @test
     */
    public function zbyliSamotariStejnehoPohlaviSdileNovyPokoj(): void
    {
        $kolej = $this->vytvorTyp('2L');
        $prvni = $this->samotar(Pohlavi::MUZ_KOD, $kolej, [self::CTVRTEK, self::PATEK, self::SOBOTA]);
        $druhy = $this->samotar(Pohlavi::MUZ_KOD, $kolej, [self::PATEK, self::SOBOTA]);
        $zena = $this->samotar(Pohlavi::ZENA_KOD, $kolej, [self::PATEK, self::SOBOTA]);

        $vysledek = $this->dopln();

        self::assertNotNull($this->kod($prvni));
        self::assertSame($this->kod($prvni), $this->kod($druhy));
        self::assertNull($this->kod($zena), 'Sama na pokoj nejde, čeká na ruční přidělení');
        self::assertSame([
            'doSkupin'       => 0,
            'doNovychSkupin' => 2,
            'novychSkupin'   => 1,
        ], $vysledek);
    }

    /**
     * @test
     */
    public function samotareBezSpolecneNociNedaDohromady(): void
    {
        $kolej = $this->vytvorTyp('2L');
        $prvni = $this->samotar(Pohlavi::MUZ_KOD, $kolej, [self::CTVRTEK, self::PATEK]);
        $druhy = $this->samotar(Pohlavi::MUZ_KOD, $kolej, [self::SOBOTA]);

        $this->dopln();

        self::assertNull($this->kod($prvni));
        self::assertNull($this->kod($druhy));
    }

    /**
     * @test
     */
    public function naHoteluNovyPokojNesmiPreplnitNoc(): void
    {
        // one room: in the night to Friday the first and a guest without a group sleep there already
        $hotel = $this->vytvorTyp('Hs-2L', posteli: 2, podtyp: PodtypPredmetu::HOTEL);
        $prvni = $this->samotar(Pohlavi::MUZ_KOD, $hotel, [self::CTVRTEK, self::PATEK]);
        $druhy = $this->samotar(Pohlavi::MUZ_KOD, $hotel, [self::PATEK, self::SOBOTA]);
        $this->objednej($this->vytvorUzivatele(Pohlavi::ZENA_KOD), $hotel, [self::CTVRTEK]);

        $vysledek = $this->dopln();

        self::assertNull($this->kod($prvni), 'Společný pokoj by ve čtvrtek zabral celý pokoj, ten tam ale není');
        self::assertNull($this->kod($druhy));
        self::assertSame(0, $vysledek['novychSkupin']);
    }

    /**
     * @test
     */
    public function vTrojluzkuMaKazdySpolecnouNocSKazdym(): void
    {
        $kolej = $this->vytvorTyp('3L');
        $ctvrtekPatek = $this->samotar(Pohlavi::MUZ_KOD, $kolej, [self::CTVRTEK, self::PATEK]);
        $patekSobota = $this->samotar(Pohlavi::MUZ_KOD, $kolej, [self::PATEK, self::SOBOTA]);
        $sobota = $this->samotar(Pohlavi::MUZ_KOD, $kolej, [self::SOBOTA]);

        $this->dopln();

        self::assertNotNull($this->kod($ctvrtekPatek));
        self::assertSame($this->kod($ctvrtekPatek), $this->kod($patekSobota));
        self::assertNull($this->kod($sobota), 'Se čtvrtkem a pátkem nemá společnou noc');
    }

    /**
     * @test
     */
    public function zmenouNaJednoluzkoNaSpolubydliciPrestaneCekat(): void
    {
        $samotar = $this->samotar(Pohlavi::MUZ_KOD, $this->vytvorTyp('2L'), [self::CTVRTEK, self::PATEK]);
        dbQuery(<<<'SQL'
DELETE FROM shop_nakupy WHERE id_uzivatele = $0 AND rok = $1
SQL,
            [
                0 => $samotar->id(),
                1 => ROCNIK,
            ],
        );
        $this->objednej($samotar, $this->vytvorTyp('1L'), [self::CTVRTEK, self::PATEK]);

        $this->skupiny()->zkontrolujPoZmeneUbytovani($samotar);

        self::assertFalse($this->skupiny()->jeSamotar((int) $samotar->id()));
    }

    /**
     * @test
     */
    public function naJednoluzkoSeSamotarPrihlasitNemuze(): void
    {
        $uzivatel = $this->vytvorUzivatele(Pohlavi::MUZ_KOD);
        $this->objednej($uzivatel, $this->vytvorTyp('1L'), [self::CTVRTEK, self::PATEK]);

        $this->expectException(ChybaUbytovaciSkupiny::class);

        $this->skupiny()->prihlasSamotare($uzivatel);
    }

    /**
     * @test
     */
    public function zalozenimSkupinyNaSpolubydliciUzNecekaAniBezUbytovani(): void
    {
        $kolej = $this->vytvorTyp('2L');
        $zakladatel = $this->samotar(Pohlavi::MUZ_KOD, $kolej, [self::CTVRTEK, self::PATEK]);
        $this->skupiny()->zaloz($zakladatel);
        self::assertFalse($this->skupiny()->jeSamotar((int) $zakladatel->id()));

        $bezUbytovani = $this->samotar(Pohlavi::MUZ_KOD, $kolej, [self::CTVRTEK, self::PATEK]);
        dbQuery(<<<'SQL'
DELETE FROM shop_nakupy WHERE id_uzivatele = $0 AND rok = $1
SQL,
            [
                0 => $bezUbytovani->id(),
                1 => ROCNIK,
            ],
        );
        $this->skupiny()->zkontrolujPoZmeneUbytovani($bezUbytovani);
        self::assertFalse($this->skupiny()->jeSamotar((int) $bezUbytovani->id()));
    }
}
