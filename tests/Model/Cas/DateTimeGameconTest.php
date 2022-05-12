<?php

namespace Gamecon\Tests\Cas;

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

    public function testZacatekRegistraciUcastniku() {
        self::assertEquals(
            DateTimeGamecon::createFromMysql(REG_GC_OD),
            DateTimeGamecon::zacatekRegistraciUcastniku(ROK, false),
            'Očekáván jiný začátek registrací, viz konstanta REG_GC_OD: ' . REG_GC_OD
        );

        self::assertEquals(
            DateTimeGamecon::createFromFormat('Y-m-d H:i:s', '2022-05-12 20:22:00'),
            DateTimeGamecon::spocitejZacatekRegistraciUcastniku(2022),
            'Očekáván jiný spočítaný začátek registrací pro rok 2022'
        );
    }

    public function testZacatekPrvniVlnyOd() {
        self::assertEquals(
            DateTimeGamecon::createFromMysql(REG_AKTIVIT_OD),
            DateTimeGamecon::zacatekPrvniVlnyOd(ROK, false),
            'Očekáván jiný začátek první vlny, viz konstanta REG_AKTIVIT_OD: ' . REG_AKTIVIT_OD
        );

        self::assertEquals(
            DateTimeGamecon::createFromFormat('Y-m-d H:i:s', '2022-05-19 20:22:00'),
            DateTimeGamecon::spoctejZacatekPrvniVlnyOd(2022),
            'Očekáván jiný začátek první vlny pro rok 2022'
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
}
