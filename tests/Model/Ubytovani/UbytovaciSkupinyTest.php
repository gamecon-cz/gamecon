<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Ubytovani;

use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Exceptions\ChybaUbytovaciSkupiny;
use Gamecon\Shop\StavPredmetu;
use Gamecon\Shop\TypPredmetu;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Ubytovani\UbytovaciSkupiny;
use PHPUnit\Framework\Attributes\DataProvider;

class UbytovaciSkupinyTest extends AbstractTestDb
{
    private const array NOCI = [
        DateTimeGamecon::PORADI_HERNIHO_DNE_CTVRTEK => 'ct',
        DateTimeGamecon::PORADI_HERNIHO_DNE_PATEK   => 'pa',
        DateTimeGamecon::PORADI_HERNIHO_DNE_SOBOTA  => 'so',
    ];

    protected static function keepTestClassDbChangesInTransaction(): bool
    {
        return true;
    }

    private function skupiny(): UbytovaciSkupiny
    {
        return new UbytovaciSkupiny(ROCNIK);
    }

    private function vytvorUzivatele(string $jmeno): \Uzivatel
    {
        $unikat = uniqid();
        dbQuery(<<<'SQL'
INSERT INTO uzivatele_hodnoty SET
    login_uzivatele = $0,
    email1_uzivatele = $1,
    jmeno_uzivatele = $2,
    prijmeni_uzivatele = 'Spolubydlící'
SQL,
            [
                0 => 'test_skupina_' . $unikat,
                1 => 'test.skupina.' . $unikat . '@example.org',
                2 => $jmeno,
            ],
        );

        return \Uzivatel::zIdUrcite(dbInsertId());
    }

    /**
     * Items of one type for Thursday to Saturday night, coded like the real ones ("2L_ct").
     * The prefix keeps the type apart from the types already in the database.
     *
     * @return array<int, int> item id by night
     */
    private function vytvorTyp(string $typ): array
    {
        $kodTypu = 'X' . uniqid() . '-' . $typ;
        $ids = [];
        foreach (self::NOCI as $noc => $pripona) {
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
            $ids[$noc] = dbInsertId();
        }

        return $ids;
    }

    /**
     * @param int[] $idsPredmetu
     */
    private function objednej(\Uzivatel $uzivatel, array $idsPredmetu): void
    {
        foreach ($idsPredmetu as $idPredmetu) {
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
    }

    private function zrusUbytovani(\Uzivatel $uzivatel): void
    {
        dbQuery(<<<'SQL'
DELETE shop_nakupy.*
FROM shop_nakupy
JOIN shop_predmety ON shop_predmety.id_predmetu = shop_nakupy.id_predmetu
WHERE shop_nakupy.id_uzivatele = $0 AND shop_nakupy.rok = $1 AND shop_predmety.typ = $2
SQL,
            [
                0 => $uzivatel->id(),
                1 => ROCNIK,
                2 => TypPredmetu::UBYTOVANI,
            ],
        );
    }

    /**
     * @test
     */
    public function zalozenaSkupinaMaZakladateleJakoJedinehoClena(): void
    {
        $zakladatel = $this->vytvorUzivatele('Zakladatel');
        $this->objednej($zakladatel, $this->vytvorTyp('2L'));

        $kod = $this->skupiny()->zaloz($zakladatel);

        self::assertMatchesRegularExpression('~^[A-Z2-9]{6}$~', $kod);
        self::assertSame($kod, $this->skupiny()->kodSkupiny((int) $zakladatel->id()));
        self::assertSame([(int) $zakladatel->id()], $this->skupiny()->idsClenu($kod));
    }

    /**
     * @test
     */
    public function bezUbytovaniSkupinuZalozitNelze(): void
    {
        $uzivatel = $this->vytvorUzivatele('Bez ubytování');

        $this->expectException(ChybaUbytovaciSkupiny::class);
        $this->expectExceptionMessage('jen s objednaným ubytováním');

        $this->skupiny()->zaloz($uzivatel);
    }

    /**
     * @test
     */
    public function naJednoluzkuSkupinuZalozitNelze(): void
    {
        $uzivatel = $this->vytvorUzivatele('Samotář');
        $this->objednej($uzivatel, $this->vytvorTyp('1L'));

        $this->expectException(ChybaUbytovaciSkupiny::class);
        $this->expectExceptionMessage('jen jedna postel');

        $this->skupiny()->zaloz($uzivatel);
    }

    /**
     * @test
     */
    public function sRuznymiTypyNaRuzneNociSkupinuZalozitNelze(): void
    {
        $uzivatel = $this->vytvorUzivatele('Přelétavý');
        $dvojluzko = $this->vytvorTyp('2L');
        $trojluzko = $this->vytvorTyp('3L');
        $this->objednej($uzivatel, [
            $dvojluzko[DateTimeGamecon::PORADI_HERNIHO_DNE_CTVRTEK],
            $trojluzko[DateTimeGamecon::PORADI_HERNIHO_DNE_PATEK],
        ]);

        $this->expectException(ChybaUbytovaciSkupiny::class);
        $this->expectExceptionMessage('na všechny noci stejný typ');

        $this->skupiny()->zaloz($uzivatel);
    }

    /**
     * @test
     */
    public function pripojitSeLzeKodemZapsanymMalymiPismenyIsMezerami(): void
    {
        $typ = $this->vytvorTyp('2L');
        $zakladatel = $this->vytvorUzivatele('Zakladatel');
        $kamarad = $this->vytvorUzivatele('Kamarád');
        $this->objednej($zakladatel, $typ);
        $this->objednej($kamarad, $typ);
        $kod = $this->skupiny()->zaloz($zakladatel);

        $this->skupiny()->pripoj($kamarad, ' ' . mb_strtolower(substr($kod, 0, 3)) . ' ' . substr($kod, 3));

        self::assertSame([(int) $zakladatel->id(), (int) $kamarad->id()], $this->skupiny()->idsClenu($kod));
    }

    /**
     * @test
     */
    public function neexistujiciKodNahlasiChybu(): void
    {
        $uzivatel = $this->vytvorUzivatele('Tápající');
        $this->objednej($uzivatel, $this->vytvorTyp('2L'));

        $this->expectException(ChybaUbytovaciSkupiny::class);
        $this->expectExceptionMessage('neexistuje');

        $this->skupiny()->pripoj($uzivatel, 'NENIKOD');
    }

    /**
     * @test
     */
    public function sJinymTypemUbytovaniSePripojitNelze(): void
    {
        $zakladatel = $this->vytvorUzivatele('Zakladatel');
        $kamarad = $this->vytvorUzivatele('Kamarád');
        $this->objednej($zakladatel, $this->vytvorTyp('2L'));
        $this->objednej($kamarad, $this->vytvorTyp('3L'));
        $kod = $this->skupiny()->zaloz($zakladatel);

        try {
            $this->skupiny()->pripoj($kamarad, $kod);
            self::fail('Do skupiny s jiným typem ubytování se nesmí dát připojit');
        } catch (ChybaUbytovaciSkupiny $chyba) {
            self::assertStringContainsString('Postel na 2L koleji', $chyba->getMessage());
            self::assertStringContainsString('Postel na 3L koleji', $chyba->getMessage());
        }
        self::assertNull($this->skupiny()->kodSkupiny((int) $kamarad->id()));
    }

    /**
     * @test
     */
    public function doPlneSkupinySePripojitNelze(): void
    {
        $typ = $this->vytvorTyp('2L');
        [$prvni, $druhy, $treti] = [
            $this->vytvorUzivatele('První'),
            $this->vytvorUzivatele('Druhý'),
            $this->vytvorUzivatele('Třetí'),
        ];
        foreach ([$prvni, $druhy, $treti] as $uzivatel) {
            $this->objednej($uzivatel, $typ);
        }
        $kod = $this->skupiny()->zaloz($prvni);
        $this->skupiny()->pripoj($druhy, $kod);

        $this->expectException(ChybaUbytovaciSkupiny::class);
        $this->expectExceptionMessage('je plná');

        $this->skupiny()->pripoj($treti, $kod);
    }

    /**
     * @test
     */
    public function clenBezUbytovaniNezabiraPostel(): void
    {
        $typ = $this->vytvorTyp('2L');
        [$prvni, $druhy, $treti] = [
            $this->vytvorUzivatele('První'),
            $this->vytvorUzivatele('Druhý'),
            $this->vytvorUzivatele('Třetí'),
        ];
        foreach ([$prvni, $druhy, $treti] as $uzivatel) {
            $this->objednej($uzivatel, $typ);
        }
        $kod = $this->skupiny()->zaloz($prvni);
        $this->skupiny()->pripoj($druhy, $kod);
        // e.g. cancelled in the admin, which does not go through the form
        $this->zrusUbytovani($druhy);

        $this->skupiny()->pripoj($treti, $kod);

        self::assertContains((int) $treti->id(), $this->skupiny()->idsClenu($kod));
    }

    /**
     * @test
     */
    public function pripojenimKJineSkupineOpustiPuvodni(): void
    {
        $typ = $this->vytvorTyp('3L');
        $prvni = $this->vytvorUzivatele('První');
        $druhy = $this->vytvorUzivatele('Druhý');
        $this->objednej($prvni, $typ);
        $this->objednej($druhy, $typ);
        $puvodniKod = $this->skupiny()->zaloz($prvni);
        $novyKod = $this->skupiny()->zaloz($druhy);

        $this->skupiny()->pripoj($prvni, $novyKod);

        self::assertSame([], $this->skupiny()->idsClenu($puvodniKod), 'Skupina bez členů zanikne');
        self::assertSame([(int) $druhy->id(), (int) $prvni->id()], $this->skupiny()->idsClenu($novyKod));
    }

    /**
     * @test
     */
    public function poZmeneTypuOpustiSkupinuSOstatnimiJinehoTypu(): void
    {
        $dvojluzko = $this->vytvorTyp('2L');
        $prvni = $this->vytvorUzivatele('První');
        $druhy = $this->vytvorUzivatele('Druhý');
        $this->objednej($prvni, $dvojluzko);
        $this->objednej($druhy, $dvojluzko);
        $kod = $this->skupiny()->zaloz($prvni);
        $this->skupiny()->pripoj($druhy, $kod);

        $this->zrusUbytovani($druhy);
        $this->objednej($druhy, $this->vytvorTyp('3L'));
        $duvod = $this->skupiny()->zkontrolujPoZmeneUbytovani($druhy);

        self::assertNotNull($duvod);
        self::assertStringContainsString($kod, $duvod);
        self::assertNull($this->skupiny()->kodSkupiny((int) $druhy->id()));
        self::assertSame([(int) $prvni->id()], $this->skupiny()->idsClenu($kod));
    }

    /**
     * @test
     */
    public function samotnyClenPoZmeneTypuVeSkupineZustane(): void
    {
        $uzivatel = $this->vytvorUzivatele('Sám');
        $this->objednej($uzivatel, $this->vytvorTyp('2L'));
        $kod = $this->skupiny()->zaloz($uzivatel);

        $this->zrusUbytovani($uzivatel);
        $this->objednej($uzivatel, $this->vytvorTyp('3L'));

        self::assertNull($this->skupiny()->zkontrolujPoZmeneUbytovani($uzivatel));
        self::assertSame($kod, $this->skupiny()->kodSkupiny((int) $uzivatel->id()));
    }

    /**
     * @test
     */
    public function poZruseniUbytovaniOpustiSkupinu(): void
    {
        $uzivatel = $this->vytvorUzivatele('Rozmyslel si to');
        $this->objednej($uzivatel, $this->vytvorTyp('2L'));
        $this->skupiny()->zaloz($uzivatel);

        $this->zrusUbytovani($uzivatel);

        self::assertNotNull($this->skupiny()->zkontrolujPoZmeneUbytovani($uzivatel));
        self::assertNull($this->skupiny()->kodSkupiny((int) $uzivatel->id()));
    }

    /**
     * @test
     */
    public function prehledUkazeClenyAUpozorniNaRozdilneNoci(): void
    {
        $typ = $this->vytvorTyp('2L');
        $prvni = $this->vytvorUzivatele('První');
        $druhy = $this->vytvorUzivatele('Druhý');
        $this->objednej($prvni, $typ);
        $this->objednej($druhy, [
            $typ[DateTimeGamecon::PORADI_HERNIHO_DNE_PATEK],
            $typ[DateTimeGamecon::PORADI_HERNIHO_DNE_SOBOTA],
        ]);
        $kod = $this->skupiny()->zaloz($prvni);
        $this->skupiny()->pripoj($druhy, $kod);

        $prehled = $this->skupiny()->prehled((int) $druhy->id());

        self::assertNotNull($prehled);
        self::assertSame($kod, $prehled['kod']);
        self::assertSame(2, $prehled['maxClenu']);
        self::assertSame([(int) $prvni->id(), (int) $druhy->id()], array_column($prehled['clenove'], 'id'));
        self::assertStringContainsString('Postel na 2L koleji', $prehled['clenove'][0]['ubytovani']);
        self::assertCount(1, $prehled['varovani']);
        self::assertStringContainsString('stejné noci', $prehled['varovani'][0]);
    }

    /**
     * @test
     */
    public function prehledBezSkupinyJeNull(): void
    {
        self::assertNull($this->skupiny()->prehled((int) $this->vytvorUzivatele('Nikdo')->id()));
    }

    /**
     * @test
     */
    public function poddotazProReportyVypiseOstatniClenySUbytovanim(): void
    {
        $typ = $this->vytvorTyp('3L');
        $prvni = $this->vytvorUzivatele('První');
        $druhy = $this->vytvorUzivatele('Druhý');
        $treti = $this->vytvorUzivatele('Třetí');
        foreach ([$prvni, $druhy, $treti] as $uzivatel) {
            $this->objednej($uzivatel, $typ);
        }
        $kod = $this->skupiny()->zaloz($prvni);
        $this->skupiny()->pripoj($druhy, $kod);
        $this->skupiny()->pripoj($treti, $kod);
        $this->zrusUbytovani($treti);

        $sqlKod = UbytovaciSkupiny::sqlKodSkupiny('uzivatele_hodnoty.id_uzivatele', ROCNIK);
        $sqlSpolubydlici = UbytovaciSkupiny::sqlSpolubydliciVeSkupine('uzivatele_hodnoty.id_uzivatele', ROCNIK);
        $radek = dbOneLine(<<<SQL
SELECT {$sqlKod} AS skupina, {$sqlSpolubydlici} AS spolubydlici
FROM uzivatele_hodnoty
WHERE uzivatele_hodnoty.id_uzivatele = $0
SQL,
            [
                0 => $prvni->id(),
            ],
        );

        self::assertSame($kod, $radek['skupina']);
        self::assertSame("Druhý Spolubydlící ({$druhy->id()})", $radek['spolubydlici']);
    }

    /**
     * @test
     */
    public function clenstviProReportMaKodAOstatniCleny(): void
    {
        $typ = $this->vytvorTyp('2L');
        $prvni = $this->vytvorUzivatele('První');
        $druhy = $this->vytvorUzivatele('Druhý');
        $this->objednej($prvni, $typ);
        $this->objednej($druhy, $typ);
        $kod = $this->skupiny()->zaloz($prvni);
        $this->skupiny()->pripoj($druhy, $kod);

        $clenstvi = $this->skupiny()->clenstviProReport();

        self::assertSame([
            'kod'          => $kod,
            'spolubydlici' => "Druhý Spolubydlící ({$druhy->id()})",
        ], $clenstvi[(int) $prvni->id()]);
        self::assertSame([
            'kod'          => $kod,
            'spolubydlici' => "První Spolubydlící ({$prvni->id()})",
        ], $clenstvi[(int) $druhy->id()]);
    }

    #[DataProvider('provideTypyAKapacity')]
    public function testKapacitaSkupinyJeZKoduTypu(string $typ, ?int $ocekavanaKapacita): void
    {
        self::assertSame($ocekavanaKapacita, UbytovaciSkupiny::maxClenu($typ));
    }

    public static function provideTypyAKapacity(): array
    {
        return [
            'kolej 2L'               => ['2L', 2],
            'kolej 3L'               => ['3L', 3],
            'hotel standard 2L'      => ['Hs-2L', 2],
            'hotel deluxe dvojbuňka' => ['Hdb-1L', 1],
            'spacák'                 => ['spacak', null],
        ];
    }
}
