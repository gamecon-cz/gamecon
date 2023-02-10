<?php

namespace Gamecon\Tests\Model\Cas;

use Gamecon\Cas\DateTimeGamecon;
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

    public function testZacatekPrvniVlnyOd() {
        self::assertEquals(
            DateTimeGamecon::createFromMysql(REG_AKTIVIT_OD),
            DateTimeGamecon::zacatekPrvniVlnyOd(ROCNIK),
            'Očekáván jiný začátek první vlny pro letošek, viz konstanta REG_AKTIVIT_OD: ' . REG_AKTIVIT_OD
        );

        self::assertEquals(
            DateTimeGamecon::createFromFormat('Y-m-d H:i:s', '2022-05-19 20:22:00'),
            DateTimeGamecon::spoctejZacatekPrvniVlnyOd(2022),
            'Očekáván jiný začátek první vlny pro rok 2022'
        );

        self::assertEquals(
            DateTimeGamecon::createFromFormat('Y-m-d H:i:s', '2016-05-10 20:16:00'),
            DateTimeGamecon::spoctejZacatekPrvniVlnyOd(2016),
            'Očekáván jiný začátek první vlny pro rok 2016'
        );
    }

    public function testPrvniHromadneOdhlasovaniOd() {
        self::assertEquals(
            DateTimeGamecon::createFromMysql(HROMADNE_ODHLASOVANI),
            DateTimeGamecon::prvniHromadneOdhlasovaniOd(),
            'Očekáván jiné datum prvního hromadného ohlašování, viz konstanta HROMADNE_ODHLASOVANI: ' . HROMADNE_ODHLASOVANI
        );

        self::assertEquals(
            DateTimeGamecon::createFromFormat('Y-m-d H:i:s', '2022-06-30 23:59:00'),
            DateTimeGamecon::spocitejPrvniHromadneOdhlasovaniOd(2022),
            'Očekáván jiné datum prvního hromadného odhlašování pro rok 2022'
        );
    }

    public function testDruheHromadneOdhlasovaniOd() {
        self::assertEquals(
            DateTimeGamecon::createFromMysql(HROMADNE_ODHLASOVANI_2),
            DateTimeGamecon::DruheHromadneOdhlasovaniOd(),
            'Očekáván jiné datum druhého hromadného ohlašování, viz konstanta HROMADNE_ODHLASOVANI_2: ' . HROMADNE_ODHLASOVANI_2
        );

        self::assertEquals(
            DateTimeGamecon::createFromFormat('Y-m-d H:i:s', '2022-07-17 23:59:00'),
            DateTimeGamecon::spocitejDruheHromadneOdhlasovaniOd(2022),
            'Očekáván jiné datum druhého hromadného odhlašování pro rok 2022'
        );
    }

    public function testDatumHromadnehoOdhlasovaniPlatiAzDenZpetne() {
        $casPrvnihoHromadnehoOdhlasovani = new \DateTimeImmutable(HROMADNE_ODHLASOVANI);
        self::assertEquals(
            $casPrvnihoHromadnehoOdhlasovani,
            DateTimeGamecon::zacatekNejblizsiVlnyOdhlasovani($casPrvnihoHromadnehoOdhlasovani),
            'Zjišťování nejbližší (první) vlny ve stejný čas jako vlna začíná by mělo vrátit začátek té samé vlny'
        );

        $presneDenPoPrvni = $casPrvnihoHromadnehoOdhlasovani->modify('+1 day');
        self::assertEquals(
            $casPrvnihoHromadnehoOdhlasovani,
            DateTimeGamecon::zacatekNejblizsiVlnyOdhlasovani($presneDenPoPrvni),
            'Zjišťování nejbližší (první) vlny ještě den poté, co vlna začíná, by mělo vrátit začátek té den staré vlny'
        );

        $casDruhehoHromadnehoOdhlasovani = new \DateTimeImmutable(HROMADNE_ODHLASOVANI_2);

        self::assertGreaterThan(
            $casPrvnihoHromadnehoOdhlasovani->modify('+1 day'),
            $casDruhehoHromadnehoOdhlasovani,
            'Prní a druhá vlna od sebe musí být nejméně den a kousek'
        );

        $denAKousekPoPrvni = $casPrvnihoHromadnehoOdhlasovani->modify('+1 day +1 second');
        self::assertEquals(
            $casDruhehoHromadnehoOdhlasovani,
            DateTimeGamecon::zacatekNejblizsiVlnyOdhlasovani($denAKousekPoPrvni),
            'Zjišťování nejbližší (první) vlny déle než den poté, co vlna začíná, by mělo vrátit začátek až následující vlny'
        );

        self::assertEquals(
            $casDruhehoHromadnehoOdhlasovani,
            DateTimeGamecon::zacatekNejblizsiVlnyOdhlasovani($casDruhehoHromadnehoOdhlasovani),
            'Zjišťování nejbližší (druhé) vlny ve stejný čas jako vlna začíná by mělo vrátit začátek té samé vlny'
        );

        $presneDenPoDruhe = $casDruhehoHromadnehoOdhlasovani->modify('+1 day');
        self::assertEquals(
            $casDruhehoHromadnehoOdhlasovani,
            DateTimeGamecon::zacatekNejblizsiVlnyOdhlasovani($presneDenPoDruhe),
            'Zjišťování nejbližší (druhé) vlny ještě den poté, co vlna začíná, by mělo vrátit začátek té den staré vlny'
        );

        $denAKousekPoDruhe = $casDruhehoHromadnehoOdhlasovani->modify('+1 day +1 second');
        self::assertEquals(
            $casDruhehoHromadnehoOdhlasovani,
            DateTimeGamecon::zacatekNejblizsiVlnyOdhlasovani($denAKousekPoDruhe),
            'Zjišťování nejbližší (druhé) vlny déle než den poté, co začíná druhá vlna, by mělo vrátit začátek druhé vlny jako poslední známé'
        );

    }
}
