<?php

namespace Gamecon\Tests\Model\Cas;

use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\SystemoveNastaveni\DatabazoveNastaveni;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use PHPUnit\Event\Telemetry\System;
use PHPUnit\Framework\TestCase;

class DateTimeGameconTest extends TestCase
{
    private static SystemoveNastaveni $systemoveNastaveni;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$systemoveNastaveni = SystemoveNastaveni::vytvorZGlobals();
    }

    /**
     * @test
     */
    public function Muzu_zjistit_kdy_je_zacatek_gameconu()
    {
        self::assertEquals(
            DateTimeGamecon::createFromMysql(GC_BEZI_OD),
            DateTimeGamecon::zacatekGameconu(),
            'Očekáván jiný začátek Gameconu, viz konstanta GC_BEZI_OD: ' . GC_BEZI_OD,
        );

        self::assertEquals(
            DateTimeGamecon::createFromFormat('Y-m-d H:i:s', '2022-07-21 07:00:00'),
            DateTimeGamecon::spocitejZacatekGameconu(2022),
            'Očekáván jiný spočítaný začátek Gameconu pro rok 2022',
        );

        self::assertEquals(
            DateTimeGamecon::createFromFormat('Y-m-d H:i:s', '2021-07-15 07:00:00'),
            DateTimeGamecon::spocitejZacatekGameconu(2021),
            'Očekáván jiný spočítaný začátek Gameconu pro rok 2021',
        );

        self::assertEquals(
            DateTimeGamecon::createFromFormat('Y-m-d H:i:s', '2016-07-21 07:00:00'),
            DateTimeGamecon::spocitejZacatekGameconu(2016),
            'Očekáván jiný spočítaný začátek Gameconu pro rok 2016',
        );
    }

    /**
     * @test
     */
    public function Muzu_zjistit_kdy_je_konec_gameconu()
    {
        self::assertEquals(
            DateTimeGamecon::createFromMysql(GC_BEZI_DO),
            DateTimeGamecon::konecGameconu(),
            'Očekáván jiný konec Gameconu, viz konstanta GC_BEZI_DO: ' . GC_BEZI_DO,
        );

        self::assertEquals(
            DateTimeGamecon::createFromFormat('Y-m-d H:i:s', '2022-07-24 21:00:00'),
            DateTimeGamecon::spocitejKonecGameconu(2022),
            'Očekáván jiný spočítaný konec Gameconu pro rok 2022',
        );

        self::assertEquals(
            DateTimeGamecon::createFromFormat('Y-m-d H:i:s', '2021-07-18 21:00:00'),
            DateTimeGamecon::spocitejKonecGameconu(2021),
            'Očekáván jiný spočítaný konec Gameconu pro rok 2021',
        );

        self::assertEquals(
            DateTimeGamecon::createFromFormat('Y-m-d H:i:s', '2016-07-24 21:00:00'),
            DateTimeGamecon::spocitejKonecGameconu(2016),
            'Očekáván jiný spočítaný konec Gameconu pro rok 2016',
        );
    }

    public function testDenKolemZacatkuGameconu()
    {
        $stredaPredGameconem2022 = DateTimeGamecon::denKolemZacatkuGameconu(DateTimeGamecon::STREDA, 2022);
        self::assertEquals(
            '2022-07-20',
            $stredaPredGameconem2022->formatDatumDb(),
            'Očekáváno jiné datum pro středu v týdnu Gameconu 2022',
        );

        $zacatekGameconu2022 = DateTimeGamecon::denKolemZacatkuGameconu(DateTimeGamecon::CTVRTEK, 2022);
        self::assertEquals(
            '2022-07-21',
            $zacatekGameconu2022->formatDatumDb(),
            'Očekáváno jiné datum pro začátek Gameconu 2022',
        );

        $konecGameconu2022 = DateTimeGamecon::denKolemZacatkuGameconu(DateTimeGamecon::NEDELE, 2022);
        self::assertEquals(
            '2022-07-24',
            $konecGameconu2022->formatDatumDb(),
            'Očekáváno jiné datum pro konec Gameconu 2022',
        );
    }

    public function testZacatekLetosnichRegistraciUcastniku()
    {
        self::assertEquals(
            DateTimeGamecon::createFromMysql(REG_GC_OD),
            DateTimeGamecon::prihlasovaniUcastnikuOd(ROCNIK),
            'Očekáván jiný začátek registrací, viz konstanta REG_GC_OD: ' . REG_GC_OD,
        );
    }

    /**
     * @dataProvider provideZacatkyRegistraciUcastniku
     */
    public function testZacatekRegistraciUcastniku(int $rok, string $ocekavanyZacatekRegistraci)
    {
        self::assertEquals(
            DateTimeGamecon::createFromFormat('Y-m-d H:i:s', $ocekavanyZacatekRegistraci),
            DateTimeGamecon::spocitejZacatekRegistraciUcastniku($rok),
            'Očekáván jiný spočítaný začátek registrací pro rok ' . $rok,
        );
    }

    public static function provideZacatkyRegistraciUcastniku(): array {
        return [
            [2023, '2023-05-11 20:23:00'],
            [2022, '2022-05-12 20:22:00'],
            [2021, '2021-05-13 20:21:00'],
            [2019, '2019-05-14 20:19:00'],
            [2018, '2018-05-15 20:18:00'],
            [2017, '2017-05-02 20:17:00'],
            [2016, '2016-05-03 20:16:00'],
            [2015, '2015-04-28 20:15:00'],
            [2014, '2014-05-01 20:00:00'],
            [2013, '2013-05-02 00:00:00'],
        ];
    }

    /**
     * @dataProvider provideZacatekPrvniVlnyOd
     */
    public function testZacatekPrvniVlnyOd(int $rocnik, string $ocekavanyZacatek)
    {
        self::assertEquals(
            DateTimeGamecon::createFromMysql($ocekavanyZacatek),
            DateTimeGamecon::spoctejKdyJePrvniVlna($rocnik),
            "Očekáván jiný spočítaný začátek první vlny pro rok $rocnik",
        );
        if ($rocnik !== ROCNIK) {
            self::assertEquals(
                DateTimeGamecon::createFromMysql($ocekavanyZacatek),
                DateTimeGamecon::prvniVlnaKdy($rocnik),
                "Očekáván jiný začátek první vlny pro rok $rocnik",
            );
        }
    }

    public static function provideZacatekPrvniVlnyOd(): array
    {
        return [
//            'současný ročník' => [ROCNIK, PRVNI_VLNA_KDY],
'2023' => [2023, '2023-05-18 20:23:00'],
'2022' => [2022, '2022-05-19 20:22:00'],
'2021' => [2021, '2021-05-20 20:21:00'],
'2019' => [2019, '2019-05-21 20:19:00'],
'2016' => [2016, '2016-05-10 20:16:00'],
        ];
    }

    /**
     * @dataProvider provideZacatekDruheVlnyOd
     */
    public function testZacatekDruheVlnyOd(int $rocnik, string $ocekavanyZacatek)
    {
        self::assertEquals(
            DateTimeGamecon::createFromMysql($ocekavanyZacatek),
            DateTimeGamecon::spocitejKdyJeDruhaVlna($rocnik),
            "Očekáván jiný spočítaný začátek druhé vlny pro rok $rocnik",
        );
        if ($rocnik !== ROCNIK) {
            self::assertEquals(
                DateTimeGamecon::createFromMysql($ocekavanyZacatek),
                DateTimeGamecon::druhaVlnaKdy($rocnik),
                "Očekáván jiný začátek druhé vlny pro rok $rocnik",
            );
        }
    }

    public static function provideZacatekDruheVlnyOd(): array
    {
        return [
            'současný ročník' => [ROCNIK, DRUHA_VLNA_KDY],
            '2023'            => [2023, '2023-06-08 20:23:00'],
            '2022'            => [2022, '2022-06-09 20:22:00'],
            '2021'            => [2021, '2021-06-10 20:21:00'],
            //            '2014'            => [2014, '2014-05-19 20:00:00'],
            //            '2013'            => [2013, '2013-06-01 20:00:00'],
        ];
    }

    /**
     * @dataProvider provideZacatekTretiVlnyOd
     */
    public function testZacatekTretiVlnyOd(int $rocnik, string $ocekavanyZacatek)
    {
        self::assertEquals(
            DateTimeGamecon::createFromMysql($ocekavanyZacatek),
            DateTimeGamecon::spocitejKdyJeTretiVlna($rocnik),
            "Očekáván jiný spočítaný začátek třetí vlny pro rok $rocnik",
        );
        if ($rocnik !== ROCNIK) {
            self::assertEquals(
                DateTimeGamecon::createFromMysql($ocekavanyZacatek),
                DateTimeGamecon::tretiVlnaKdy($rocnik),
                "Očekáván jiný začátek třetí vlny pro rok $rocnik",
            );
        }
    }

    public static function provideZacatekTretiVlnyOd(): array
    {
        return [
            'současný ročník' => [ROCNIK, TRETI_VLNA_KDY],
            '2023'            => [2023, '2023-07-01 20:23:00'],
            '2022'            => [2022, '2022-07-01 20:22:00'],
            //            '2014'            => [2014, '2014-06-09 20:00:00'],
        ];
    }

    public function testPrvniHromadneOdhlasovani()
    {
        $ted                = new DateTimeImmutableStrict();
        $systemoveNastaveni = $this->dejSystemoveNastaveni($ted);
        $tretiVlnaKdy       = $systemoveNastaveni->tretiVlnaKdy();
        self::assertEquals(
            $tretiVlnaKdy->modify('-10 minutes'),
            DateTimeGamecon::prvniHromadneOdhlasovani(),
            'Očekáváno jiné datum prvního hromadného ohlašování',
        );

        $systemoveNastaveni2023 = $this->dejSystemoveNastaveni($ted, 2023);
        $tretiVlna2023          = $systemoveNastaveni2023->tretiVlnaKdy();
        self::assertEquals(
            $tretiVlna2023->modify('-10 minutes'),
            DateTimeGamecon::spocitejPrvniHromadneOdhlasovani(2023),
            'Očekáváno jiné datum spočítaného prvního hromadného odhlašování pro rok 2023',
        );
        self::assertEquals(
            new DateTimeGamecon('2023-07-01 20:13:00'),
            DateTimeGamecon::spocitejPrvniHromadneOdhlasovani(2023),
            'Očekáváno jiné datum spočítaného prvního hromadného odhlašování pro rok 2023',
        );
    }

    public function testDruheHromadneOdhlasovani()
    {
        $ted                      = new DateTimeImmutableStrict();
        $systemoveNastaveni       = $this->dejSystemoveNastaveni($ted);
        $prvniHromadneOdhlasovani = $systemoveNastaveni->prvniHromadneOdhlasovani();
        self::assertEquals(
            $prvniHromadneOdhlasovani->modify('+9 days')->setTime(0, 0, 0),
            DateTimeGamecon::druheHromadneOdhlasovani(),
            'Očekáváno jiné datum druhého hromadného ohlašování',
        );

        $systemoveNastaveni2023       = $this->dejSystemoveNastaveni($ted, 2023);
        $prvniHromadneOdhlasovani2023 = $systemoveNastaveni2023->prvniHromadneOdhlasovani();
        self::assertEquals(
            $prvniHromadneOdhlasovani2023->modify('+9 days')->setTime(0, 0, 0),
            DateTimeGamecon::spocitejDruheHromadneOdhlasovani(2023),
            'Očekáváno jiné datum spočítaného druhého hromadného odhlašování pro rok 2023',
        );
        self::assertEquals(
            new DateTimeGamecon('2023-07-10 00:00:00'),
            DateTimeGamecon::spocitejDruheHromadneOdhlasovani(2023),
            'Očekáváno jiné datum spočítaného druhého hromadného odhlašování pro rok 2023',
        );
    }

    public function testTretiHromadneOdhlasovani()
    {
        $ted                      = new DateTimeImmutableStrict();
        $systemoveNastaveni       = $this->dejSystemoveNastaveni($ted);
        $druheHromadneOdhlasovani = $systemoveNastaveni->druheHromadneOdhlasovani();
        self::assertEquals(
            $druheHromadneOdhlasovani->modify('+7 days'),
            DateTimeGamecon::tretiHromadneOdhlasovani(),
            'Očekáváno jiné datum třetího hromadného ohlašování',
        );

        $systemoveNastaveni2023   = $this->dejSystemoveNastaveni($ted, 2023);
        $druheHromadneOdhlasovani = $systemoveNastaveni2023->druheHromadneOdhlasovani();
        self::assertEquals(
            $druheHromadneOdhlasovani->modify('+7 days'),
            DateTimeGamecon::spocitejTretiHromadneOdhlasovani(2023),
            'Očekáváno jiné datum spočítaného třetího hromadného odhlašování pro rok 2023',
        );
        self::assertEquals(
            new DateTimeGamecon('2023-07-17 00:00:00'),
            DateTimeGamecon::spocitejTretiHromadneOdhlasovani(2023),
            'Očekáváno jiné datum spočítaného třetího hromadného odhlašování pro rok 2023',
        );
    }

    public function testDatumHromadnehoOdhlasovaniPlatiAzDenZpetne()
    {
        // PRVNÍ
        $casPrvnihoHromadnehoOdhlasovani = DateTimeImmutableStrict::createFromInterface(DateTimeGamecon::prvniHromadneOdhlasovani());
        $this->testPrvnihoHromadnehoOdhlasovaniJakoNejblizsiho($casPrvnihoHromadnehoOdhlasovani);

        // DRUHÉ
        $casDruhehoHromadnehoOdhlasovani = DateTimeImmutableStrict::createFromInterface(DateTimeGamecon::druheHromadneOdhlasovani());
        $this->testDruhehoHromadnehoOdhlasovaniJakoNejblizsiho($casPrvnihoHromadnehoOdhlasovani, $casDruhehoHromadnehoOdhlasovani);

        // TŘETÍ
        $casTretihoHromadnehoOdhlasovani = DateTimeImmutableStrict::createFromInterface(DateTimeGamecon::tretiHromadneOdhlasovani());
        $this->testTretihoHromadnehoOdhlasovaniJakoNejblizsiho($casDruhehoHromadnehoOdhlasovani, $casTretihoHromadnehoOdhlasovani);
    }

    private function testPrvnihoHromadnehoOdhlasovaniJakoNejblizsiho(DateTimeImmutableStrict $casPrvnihoHromadnehoOdhlasovani)
    {
        $nastaveniCasPrvnihoHromadnehoOdhlasovani = $this->dejSystemoveNastaveni($casPrvnihoHromadnehoOdhlasovani);
        self::assertEquals(
            $casPrvnihoHromadnehoOdhlasovani,
            DateTimeGamecon::nejblizsiHromadneOdhlasovaniKdy($nastaveniCasPrvnihoHromadnehoOdhlasovani),
            'Zjišťování nejbližší (první) vlny ve stejný čas jako vlna začíná by mělo vrátit začátek té samé vlny',
        );

        $presneDenPoPrvni         = $casPrvnihoHromadnehoOdhlasovani->modify('+1 day');
        $nastveniPresneDenPoPrvni = $this->dejSystemoveNastaveni($presneDenPoPrvni);
        self::assertEquals(
            $casPrvnihoHromadnehoOdhlasovani,
            DateTimeGamecon::nejblizsiHromadneOdhlasovaniKdy($nastveniPresneDenPoPrvni),
            'Zjišťování nejbližší (první) vlny ještě den poté, co vlna začíná, by mělo vrátit začátek té den staré vlny',
        );
        $this->testHromadnehoOdhlasovaniJakoNejblizsiho($casPrvnihoHromadnehoOdhlasovani, 1);
    }

    private function testHromadnehoOdhlasovaniJakoNejblizsiho(
        DateTimeImmutableStrict $casHromadnehoOdhlasovani,
        int                     $poradiOdhlasovani,
    )
    {
        $nastaveniCasPrvnihoHromadnehoOdhlasovani = $this->dejSystemoveNastaveni($casHromadnehoOdhlasovani);
        self::assertEquals(
            $casHromadnehoOdhlasovani,
            DateTimeGamecon::nejblizsiHromadneOdhlasovaniKdy($nastaveniCasPrvnihoHromadnehoOdhlasovani),
            "Zjišťování nejbližšího ($poradiOdhlasovani) odhlašování ve stejný čas jako nějaké odhlašování začíná by mělo vrátit začátek právě toho odhlašování",
        );

        $presneDenPo              = $casHromadnehoOdhlasovani->modify('+1 day');
        $nastaveniSTedPresneDenPo = $this->dejSystemoveNastaveni($presneDenPo);
        self::assertEquals(
            $casHromadnehoOdhlasovani,
            DateTimeGamecon::nejblizsiHromadneOdhlasovaniKdy($nastaveniSTedPresneDenPo),
            "Zjišťování nejbližšího ($poradiOdhlasovani) odhlašování ještě den poté, co začíná, by mělo vrátit začátek toho den starého odhlašování",
        );
    }

    private function testDruhehoHromadnehoOdhlasovaniJakoNejblizsiho(
        DateTimeImmutableStrict $casPrvnihoHromadnehoOdhlasovani,
        DateTimeImmutableStrict $casDruhehoHromadnehoOdhlasovani,
    )
    {
        $this->testDalsihoHromadnehoOdhlasovaniJakoNejblizsiho(
            $casPrvnihoHromadnehoOdhlasovani,
            $casDruhehoHromadnehoOdhlasovani,
            2,
        );
    }

    private function testDalsihoHromadnehoOdhlasovaniJakoNejblizsiho(
        DateTimeImmutableStrict $casPredchozihoHromadnehoOdhlasovani,
        DateTimeImmutableStrict $casSoucasnehoHromadnehoOdhlasovani,
        int                     $poradiSoucasneho,
    )
    {
        $poradiPrechoziho = $poradiSoucasneho - 1;

        self::assertGreaterThan(
            $casPredchozihoHromadnehoOdhlasovani->modify('+1 day'),
            $casSoucasnehoHromadnehoOdhlasovani,
            "Předchozí ({$poradiPrechoziho}.) a současné ({$poradiSoucasneho}.) odhlašování od sebe musí být nejméně den a kousek",
        );

        $denAKousekPoPrvni          = $casPredchozihoHromadnehoOdhlasovani->modify('+1 day +1 second');
        $nastaveniDenAKousekPoPrvni = $this->dejSystemoveNastaveni($denAKousekPoPrvni);
        self::assertEquals(
            $casSoucasnehoHromadnehoOdhlasovani,
            DateTimeGamecon::nejblizsiHromadneOdhlasovaniKdy($nastaveniDenAKousekPoPrvni),
            "Zjišťování nejbližšího odhlašování déle než den poté, co {$poradiPrechoziho}. odhlašování začíná, by mělo vrátit začátek až následujícího, {$poradiSoucasneho}. odhlašování",
        );

        $this->testHromadnehoOdhlasovaniJakoNejblizsiho($casSoucasnehoHromadnehoOdhlasovani, 2);
    }

    private function testTretihoHromadnehoOdhlasovaniJakoNejblizsiho(
        DateTimeImmutableStrict $casDruhehoHromadnehoOdhlasovani,
        DateTimeImmutableStrict $casTretihoHromadnehoOdhlasovani,
    )
    {
        $this->testPoslednihoHromadnehoOdhlasovaniJakoNejblizsiho(
            $casDruhehoHromadnehoOdhlasovani,
            $casTretihoHromadnehoOdhlasovani,
            3,
        );
    }

    private function testPoslednihoHromadnehoOdhlasovaniJakoNejblizsiho(
        DateTimeImmutableStrict $casPrechozihoHromadnehoOdhlasovani,
        DateTimeImmutableStrict $casPoslednihoHromadnehoOdhlasovani,
        int                     $poradiSoucasneho,
    )
    {
        $this->testDalsihoHromadnehoOdhlasovaniJakoNejblizsiho(
            $casPrechozihoHromadnehoOdhlasovani,
            $casPoslednihoHromadnehoOdhlasovani,
            $poradiSoucasneho,
        );

        $denAKousekPoPoslednim              = $casPoslednihoHromadnehoOdhlasovani->modify('+1 day +1 second');
        $nastaveniSTedDenAKousekPoPoslednim = $this->dejSystemoveNastaveni($denAKousekPoPoslednim);
        self::assertEquals(
            $casPoslednihoHromadnehoOdhlasovani,
            DateTimeGamecon::nejblizsiHromadneOdhlasovaniKdy($nastaveniSTedDenAKousekPoPoslednim),
            "Zjišťování nejbližšího odhlašování déle než den poté, co {$poradiSoucasneho}. odhlašování začíná, by mělo i tak vrátit začátek {$poradiSoucasneho}. odhlašování, protože je poslední",
        );
    }

    private function dejSystemoveNastaveni(
        DateTimeImmutableStrict $ted,
        int                     $rocnik = ROCNIK,
    ): SystemoveNastaveni
    {
        return new SystemoveNastaveni(
            $rocnik,
            $ted,
            false,
            false,
            DatabazoveNastaveni::vytvorZGlobals(),
            PROJECT_ROOT_DIR,
        );
    }

    public function testDatumDneVTydnuDoData()
    {
        self::assertSame(
            '2021-07-25',
            DateTimeGamecon::dejDatumDneVTydnuDoData(
                DateTimeGamecon::NEDELE,
                new DateTimeGamecon('2021-07-31'), // sobota
            )->format(DateTimeGamecon::FORMAT_DATUM_DB),
            'Měli bychom dostat neděli v předchozím týdnu',
        );
        self::assertSame(
            '2023-05-11',
            DateTimeGamecon::dejDatumDneVTydnuDoData(
                DateTimeGamecon::CTVRTEK,
                new DateTimeGamecon('2023-05-14'), // neděle
            )->format(DateTimeGamecon::FORMAT_DATUM_DB),
            'Měli bychom dostat čtvrtek ve stejném týdnu i z datumu s nedělí',
        );

        self::assertSame(
            '2023-05-11',
            DateTimeGamecon::dejDatumDneVTydnuDoData(
                DateTimeGamecon::CTVRTEK,
                new DateTimeGamecon('2023-05-11'), // čtvrtek
            )->format(DateTimeGamecon::FORMAT_DATUM_DB),
            'Měli bychom dostat zase stejný čtvrtek',
        );
    }

    public function testDatumDneVTydnuOdData()
    {
        self::assertSame(
            '2023-05-11',
            DateTimeGamecon::dejDatumDneVTydnuOdData(
                DateTimeGamecon::CTVRTEK,
                new DateTimeGamecon('2023-05-08'),
            )->format(DateTimeGamecon::FORMAT_DATUM_DB),
            'Měli bychom dostat čtvrtek ve stejném týdnu i z datumu s pondělím',
        );

        self::assertSame(
            '2023-05-11',
            DateTimeGamecon::dejDatumDneVTydnuOdData(
                DateTimeGamecon::CTVRTEK,
                new DateTimeGamecon('2023-05-11'),
            )->format(DateTimeGamecon::FORMAT_DATUM_DB),
            'Měli bychom dostat zase stejný čtvrtek',
        );
    }

    /**
     * @test
     */
    public function Konec_registraci_ucastniku_je_stejny_jako_konec_gameconu()
    {
        foreach (range(2020, (int)date('Y') + 1) as $rocnik) {
            self::assertEquals(
                DateTimeGamecon::spocitejKonecGameconu($rocnik),
                DateTimeGamecon::spocitejKonecRegistraciUcastniku($rocnik),
                "Očekáván jiný spočítaný konec registací účastníků pro ročník $rocnik",
            );
        }
        self::assertEquals(
            DateTimeGamecon::konecGameconu(),
            DateTimeGamecon::prihlasovaniUcastnikuDo(),
            "Očekáván jiný konec registací účastníků pro současný ročník",
        );
    }

    /**
     * @dataProvider provideCasAPoradiHromadnehoOdhlasovani
     * @test
     */
    public function Muzu_ziskat_poradi_hromadneho_odhlasovani(
        int                $ocekavanePoradi,
        \DateTimeInterface $casHromadnehoOdhlasovani,
    )
    {
        self::assertSame(
            $ocekavanePoradi,
            DateTimeGamecon::poradiHromadnehoOdhlasovani($casHromadnehoOdhlasovani, self::$systemoveNastaveni),
        );
    }

    public static function provideCasAPoradiHromadnehoOdhlasovani(): array
    {
        return [
            'první; 2023'            => [1, DateTimeGamecon::spocitejPrvniHromadneOdhlasovani(2023)],
            'druhé; 2023'            => [2, DateTimeGamecon::spocitejDruheHromadneOdhlasovani(2023)],
            'třetí; 2023'            => [3, DateTimeGamecon::spocitejTretiHromadneOdhlasovani(2023)],
            'první; současý ročník'  => [1, DateTimeGamecon::prvniHromadneOdhlasovani()],
            'druhé; současný ročník' => [2, DateTimeGamecon::druheHromadneOdhlasovani()],
            'třetí; současný ročník' => [3, DateTimeGamecon::tretiHromadneOdhlasovani()],
        ];
    }
}
