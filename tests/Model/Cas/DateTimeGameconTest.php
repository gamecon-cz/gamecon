<?php

namespace Gamecon\Tests\Model\Cas;

use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\SystemoveNastaveni\DatabazoveNastaveni;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use PHPUnit\Framework\TestCase;

class DateTimeGameconTest extends TestCase
{

    public function testZacatekGameconu() {
        self::assertEquals(
            DateTimeGamecon::createFromMysql(GC_BEZI_OD),
            DateTimeGamecon::zacatekGameconu(),
            'Očekáván jiný začátek Gameconu, viz konstanta GC_BEZI_OD: ' . GC_BEZI_OD
        );

        self::assertEquals(
            DateTimeGamecon::createFromFormat('Y-m-d H:i:s', '2022-07-21 07:00:00'),
            DateTimeGamecon::spocitejZacatekGameconu(2022),
            'Očekáván jiný spočítaný začátek Gameconu pro rok 2022'
        );

        self::assertEquals(
            DateTimeGamecon::createFromFormat('Y-m-d H:i:s', '2021-07-15 07:00:00'),
            DateTimeGamecon::spocitejZacatekGameconu(2021),
            'Očekáván jiný spočítaný začátek Gameconu pro rok 2021'
        );

        self::assertEquals(
            DateTimeGamecon::createFromFormat('Y-m-d H:i:s', '2016-07-21 07:00:00'),
            DateTimeGamecon::spocitejZacatekGameconu(2016),
            'Očekáván jiný spočítaný začátek Gameconu pro rok 2016'
        );
    }

    public function testKonecGameconu() {
        self::assertEquals(
            DateTimeGamecon::createFromMysql(GC_BEZI_DO),
            DateTimeGamecon::konecGameconu(),
            'Očekáván jiný konec Gameconu, viz konstanta GC_BEZI_DO: ' . GC_BEZI_DO
        );

        self::assertEquals(
            DateTimeGamecon::createFromFormat('Y-m-d H:i:s', '2022-07-24 21:00:00'),
            DateTimeGamecon::spocitejKonecGameconu(2022),
            'Očekáván jiný spočítaný konec Gameconu pro rok 2022'
        );

        self::assertEquals(
            DateTimeGamecon::createFromFormat('Y-m-d H:i:s', '2021-07-18 21:00:00'),
            DateTimeGamecon::spocitejKonecGameconu(2021),
            'Očekáván jiný spočítaný konec Gameconu pro rok 2021'
        );

        self::assertEquals(
            DateTimeGamecon::createFromFormat('Y-m-d H:i:s', '2016-07-24 21:00:00'),
            DateTimeGamecon::spocitejKonecGameconu(2016),
            'Očekáván jiný spočítaný konec Gameconu pro rok 2016'
        );
    }

    public function testDenKolemZacatkuGameconu() {
        $stredaPredGameconem2022 = DateTimeGamecon::denKolemZacatkuGameconu(DateTimeGamecon::STREDA, 2022);
        self::assertEquals(
            '2022-07-20',
            $stredaPredGameconem2022->formatDatumDb(),
            'Očekáváno jiné datum pro středu v týdnu Gameconu 2022'
        );

        $zacatekGameconu2022 = DateTimeGamecon::denKolemZacatkuGameconu(DateTimeGamecon::CTVRTEK, 2022);
        self::assertEquals(
            '2022-07-21',
            $zacatekGameconu2022->formatDatumDb(),
            'Očekáváno jiné datum pro začátek Gameconu 2022'
        );

        $konecGameconu2022 = DateTimeGamecon::denKolemZacatkuGameconu(DateTimeGamecon::NEDELE, 2022);
        self::assertEquals(
            '2022-07-24',
            $konecGameconu2022->formatDatumDb(),
            'Očekáváno jiné datum pro konec Gameconu 2022'
        );
    }

    public function testZacatekLetosnichRegistraciUcastniku() {
        self::assertEquals(
            DateTimeGamecon::createFromMysql(REG_GC_OD),
            DateTimeGamecon::zacatekRegistraciUcastniku(ROCNIK),
            'Očekáván jiný začátek registrací, viz konstanta REG_GC_OD: ' . REG_GC_OD
        );
    }

    /**
     * @dataProvider provideZacatkyRegistraciUcastniku
     */
    public function testZacatekRegistraciUcastniku(int $rok, string $ocekavanyZacatekRegistraci) {
        self::assertEquals(
            DateTimeGamecon::createFromFormat('Y-m-d H:i:s', $ocekavanyZacatekRegistraci),
            DateTimeGamecon::spocitejZacatekRegistraciUcastniku($rok),
            'Očekáván jiný spočítaný začátek registrací pro rok ' . $rok
        );
    }

    public function provideZacatkyRegistraciUcastniku(): array {
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
    public function testZacatekPrvniVlnyOd(int $rocnik, string $ocekavanyZacatek) {
        self::assertEquals(
            DateTimeGamecon::createFromMysql($ocekavanyZacatek),
            DateTimeGamecon::spoctejZacatekPrvniVlnyOd($rocnik),
            "Očekáván jiný spočítaný začátek první vlny pro rok $rocnik"
        );
        if ($rocnik !== ROCNIK) {
            self::assertEquals(
                DateTimeGamecon::createFromMysql($ocekavanyZacatek),
                DateTimeGamecon::zacatekPrvniVlnyOd($rocnik),
                "Očekáván jiný začátek první vlny pro rok $rocnik"
            );
        }
    }

    public function provideZacatekPrvniVlnyOd(): array {
        return [
//            'současný ročník' => [ROCNIK, ZACATEK_PRVNI_VLNY],
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
    public function testZacatekDruheVlnyOd(int $rocnik, string $ocekavanyZacatek) {
        self::assertEquals(
            DateTimeGamecon::createFromMysql($ocekavanyZacatek),
            DateTimeGamecon::spoctejZacatekDruheVlnyOd($rocnik),
            "Očekáván jiný spočítaný začátek druhé vlny pro rok $rocnik"
        );
        if ($rocnik !== ROCNIK) {
            self::assertEquals(
                DateTimeGamecon::createFromMysql($ocekavanyZacatek),
                DateTimeGamecon::zacatekDruheVlnyOd($rocnik),
                "Očekáván jiný začátek druhé vlny pro rok $rocnik"
            );
        }
    }

    public function provideZacatekDruheVlnyOd(): array {
        return [
            'současný ročník' => [ROCNIK, ZACATEK_DRUHE_VLNY],
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
    public function testZacatekTretiVlnyOd(int $rocnik, string $ocekavanyZacatek) {
        self::assertEquals(
            DateTimeGamecon::createFromMysql($ocekavanyZacatek),
            DateTimeGamecon::spoctejZacatekTretiVlnyOd($rocnik),
            "Očekáván jiný spočítaný začátek třetí vlny pro rok $rocnik"
        );
        if ($rocnik !== ROCNIK) {
            self::assertEquals(
                DateTimeGamecon::createFromMysql($ocekavanyZacatek),
                DateTimeGamecon::zacatekTretiVlnyOd($rocnik),
                "Očekáván jiný začátek třetí vlny pro rok $rocnik"
            );
        }
    }

    public function provideZacatekTretiVlnyOd(): array {
        return [
            'současný ročník' => [ROCNIK, ZACATEK_TRETI_VLNY],
            '2023'            => [2023, '2023-07-01 20:23:00'],
            '2022'            => [2022, '2022-07-01 20:22:00'],
//            '2014'            => [2014, '2014-06-09 20:00:00'],
        ];
    }

    public function testPrvniHromadneOdhlasovaniOd() {
        self::assertEquals(
            DateTimeGamecon::createFromMysql(HROMADNE_ODHLASOVANI_1),
            DateTimeGamecon::prvniHromadneOdhlasovaniDo(),
            'Očekáván jiné datum prvního hromadného ohlašování, viz konstanta HROMADNE_ODHLASOVANI_1: ' . HROMADNE_ODHLASOVANI_1
        );

        self::assertEquals(
            DateTimeGamecon::createFromFormat('Y-m-d H:i:s', '2023-06-30 23:59:00'),
            DateTimeGamecon::spocitejPrvniHromadneOdhlasovaniDo(2023),
            'Očekáván jiné datum prvního hromadného odhlašování pro rok 2023'
        );
    }

    public function testDruheHromadneOdhlasovaniOd() {
        self::assertEquals(
            DateTimeGamecon::createFromMysql(HROMADNE_ODHLASOVANI_2),
            DateTimeGamecon::druheHromadneOdhlasovaniDo(),
            'Očekáváno jiné datum druhého hromadného ohlašování, viz konstanta HROMADNE_ODHLASOVANI_2: ' . HROMADNE_ODHLASOVANI_2
        );

        self::assertEquals(
            DateTimeGamecon::createFromFormat('Y-m-d H:i:s', '2023-07-09 23:59:00'),
            DateTimeGamecon::spocitejDruheHromadneOdhlasovaniDo(2023),
            'Očekáváno jiné datum druhého hromadného odhlašování pro rok 2023'
        );
    }

    public function testTretiHromadneOdhlasovaniOd() {
        self::assertEquals(
            DateTimeGamecon::createFromMysql(HROMADNE_ODHLASOVANI_3),
            DateTimeGamecon::tretiHromadneOdhlasovaniDo(),
            'Očekáváno jiné datum třetího hromadného ohlašování, viz konstanta HROMADNE_ODHLASOVANI_3: ' . HROMADNE_ODHLASOVANI_3
        );

        self::assertEquals(
            DateTimeGamecon::createFromFormat('Y-m-d H:i:s', '2023-07-16 23:59:00'),
            DateTimeGamecon::spocitejTretiHromadneOdhlasovaniDo(2023),
            'Očekáváno jiné datum třetího hromadného odhlašování pro rok 2023'
        );
    }

    public function testDatumHromadnehoOdhlasovaniPlatiAzDenZpetne() {
        // PRVNÍ VLNA
        $casPrvnihoHromadnehoOdhlasovani = new DateTimeImmutableStrict(HROMADNE_ODHLASOVANI_1);
        $this->testPrvnihoHromadnehoOdhlasovani($casPrvnihoHromadnehoOdhlasovani);

        // DRUHÁ VLNA
        $casDruhehoHromadnehoOdhlasovani = new DateTimeImmutableStrict(HROMADNE_ODHLASOVANI_2);
        $this->testDruhehoHromadnehoOdhlasovani($casPrvnihoHromadnehoOdhlasovani, $casDruhehoHromadnehoOdhlasovani);

        // TŘETÍ VLNA
        $casTretihoHromadnehoOdhlasovani = new DateTimeImmutableStrict(HROMADNE_ODHLASOVANI_3);
        $this->testTretihoHromadnehoOdhlasovani($casDruhehoHromadnehoOdhlasovani, $casTretihoHromadnehoOdhlasovani);
    }

    private function testPrvnihoHromadnehoOdhlasovani(DateTimeImmutableStrict $casPrvnihoHromadnehoOdhlasovani) {
        $nastaveniCasPrvnihoHromadnehoOdhlasovani = $this->dejSystemoveNastaveni($casPrvnihoHromadnehoOdhlasovani);
        self::assertEquals(
            $casPrvnihoHromadnehoOdhlasovani,
            DateTimeGamecon::zacatekNejblizsiVlnyOdhlasovani($nastaveniCasPrvnihoHromadnehoOdhlasovani),
            'Zjišťování nejbližší (první) vlny ve stejný čas jako vlna začíná by mělo vrátit začátek té samé vlny'
        );

        $presneDenPoPrvni         = $casPrvnihoHromadnehoOdhlasovani->modify('+1 day');
        $nastveniPresneDenPoPrvni = $this->dejSystemoveNastaveni($presneDenPoPrvni);
        self::assertEquals(
            $casPrvnihoHromadnehoOdhlasovani,
            DateTimeGamecon::zacatekNejblizsiVlnyOdhlasovani($nastveniPresneDenPoPrvni),
            'Zjišťování nejbližší (první) vlny ještě den poté, co vlna začíná, by mělo vrátit začátek té den staré vlny'
        );
    }

    private function testDruhehoHromadnehoOdhlasovani(
        DateTimeImmutableStrict $casPrvnihoHromadnehoOdhlasovani,
        DateTimeImmutableStrict $casDruhehoHromadnehoOdhlasovani
    ) {
        self::assertGreaterThan(
            $casPrvnihoHromadnehoOdhlasovani->modify('+1 day'),
            $casDruhehoHromadnehoOdhlasovani,
            'Prní a druhá vlna od sebe musí být nejméně den a kousek'
        );

        $denAKousekPoPrvni          = $casPrvnihoHromadnehoOdhlasovani->modify('+1 day +1 second');
        $nastaveniDenAKousekPoPrvni = $this->dejSystemoveNastaveni($denAKousekPoPrvni);
        self::assertEquals(
            $casDruhehoHromadnehoOdhlasovani,
            DateTimeGamecon::zacatekNejblizsiVlnyOdhlasovani($nastaveniDenAKousekPoPrvni),
            'Zjišťování nejbližší (první) vlny déle než den poté, co vlna začíná, by mělo vrátit začátek až následující vlny'
        );

        $nastaveniCasDruhehoHromadnehoOdhlasovani = $this->dejSystemoveNastaveni($casDruhehoHromadnehoOdhlasovani);
        self::assertEquals(
            $casDruhehoHromadnehoOdhlasovani,
            DateTimeGamecon::zacatekNejblizsiVlnyOdhlasovani($nastaveniCasDruhehoHromadnehoOdhlasovani),
            'Zjišťování nejbližší (druhé) vlny ve stejný čas jako vlna začíná by mělo vrátit začátek té samé vlny'
        );

        $presneDenPoDruhe          = $casDruhehoHromadnehoOdhlasovani->modify('+1 day');
        $nastaveniPresneDenPoDruhe = $this->dejSystemoveNastaveni($presneDenPoDruhe);
        self::assertEquals(
            $casDruhehoHromadnehoOdhlasovani,
            DateTimeGamecon::zacatekNejblizsiVlnyOdhlasovani($nastaveniPresneDenPoDruhe),
            'Zjišťování nejbližší (druhé) vlny ještě den poté, co vlna začíná, by mělo vrátit začátek té den staré vlny'
        );
    }

    private function testTretihoHromadnehoOdhlasovani(
        DateTimeImmutableStrict $casDruhehoHromadnehoOdhlasovani,
        DateTimeImmutableStrict $casTretihoHromadnehoOdhlasovani
    ) {
        self::assertGreaterThan(
            $casDruhehoHromadnehoOdhlasovani->modify('+1 day'),
            $casTretihoHromadnehoOdhlasovani,
            'Druhá a třetí vlna od sebe musí být nejméně den a kousek'
        );

        $denAKousekPoTreti          = $casDruhehoHromadnehoOdhlasovani->modify('+1 day +1 second');
        $nastaveniDenAKousekPoTreti = $this->dejSystemoveNastaveni($denAKousekPoTreti);
        self::assertEquals(
            $casTretihoHromadnehoOdhlasovani,
            DateTimeGamecon::zacatekNejblizsiVlnyOdhlasovani($nastaveniDenAKousekPoTreti),
            'Zjišťování nejbližší (druhé) vlny déle než den poté, co vlna začíná, by mělo vrátit začátek až následující vlny'
        );

        $nastaveniCasTretihoHromadnehoOdhlasovani = $this->dejSystemoveNastaveni($casTretihoHromadnehoOdhlasovani);
        self::assertEquals(
            $casTretihoHromadnehoOdhlasovani,
            DateTimeGamecon::zacatekNejblizsiVlnyOdhlasovani($nastaveniCasTretihoHromadnehoOdhlasovani),
            'Zjišťování nejbližší (třetí) vlny ve stejný čas jako vlna začíná by mělo vrátit začátek té samé vlny'
        );

        $presneDenPoTreti          = $casTretihoHromadnehoOdhlasovani->modify('+1 day');
        $nastaveniPresneDenPoDruhe = $this->dejSystemoveNastaveni($presneDenPoTreti);
        self::assertEquals(
            $casTretihoHromadnehoOdhlasovani,
            DateTimeGamecon::zacatekNejblizsiVlnyOdhlasovani($nastaveniPresneDenPoDruhe),
            'Zjišťování nejbližší (třetí) vlny ještě den poté, co vlna začíná, by mělo vrátit začátek té den staré vlny'
        );

        $denAKousekPoTreti          = $casTretihoHromadnehoOdhlasovani->modify('+1 day +1 second');
        $nastaveniDenAKousekPoTreti = $this->dejSystemoveNastaveni($denAKousekPoTreti);
        self::assertEquals(
            $casTretihoHromadnehoOdhlasovani,
            DateTimeGamecon::zacatekNejblizsiVlnyOdhlasovani($nastaveniDenAKousekPoTreti),
            'Zjišťování nejbližší (třetí) vlny déle než den poté, co začíná druhá vlna, by mělo vrátit začátek třetí vlny jako poslední známé'
        );
    }

    private function dejSystemoveNastaveni(DateTimeImmutableStrict $ted): SystemoveNastaveni {
        return new SystemoveNastaveni(
            ROCNIK,
            $ted,
            false,
            false,
            DatabazoveNastaveni::vytvorZGlobals()
        );
    }

    public function testDatumDneVTydnuDoData() {
        self::assertSame(
            '2023-05-11',
            DateTimeGamecon::dejDatumDneVTydnuDoData(
                DateTimeGamecon::CTVRTEK,
                new DateTimeGamecon('2023-05-14')
            )->format(DateTimeGamecon::FORMAT_DATUM_DB),
            'Měli bychom dostat čtvrtek ve stejném týdnu i z datumu s nedělí'
        );

        self::assertSame(
            '2023-05-11',
            DateTimeGamecon::dejDatumDneVTydnuDoData(
                DateTimeGamecon::CTVRTEK,
                new DateTimeGamecon('2023-05-11')
            )->format(DateTimeGamecon::FORMAT_DATUM_DB),
            'Měli bychom dostat zase stejný čtvrtek'
        );
    }

    public function testDatumDneVTydnuOdData() {
        self::assertSame(
            '2023-05-11',
            DateTimeGamecon::dejDatumDneVTydnuOdData(
                DateTimeGamecon::CTVRTEK,
                new DateTimeGamecon('2023-05-08')
            )->format(DateTimeGamecon::FORMAT_DATUM_DB),
            'Měli bychom dostat čtvrtek ve stejném týdnu i z datumu s pondělím'
        );

        self::assertSame(
            '2023-05-11',
            DateTimeGamecon::dejDatumDneVTydnuOdData(
                DateTimeGamecon::CTVRTEK,
                new DateTimeGamecon('2023-05-11')
            )->format(DateTimeGamecon::FORMAT_DATUM_DB),
            'Měli bychom dostat zase stejný čtvrtek'
        );
    }
}
