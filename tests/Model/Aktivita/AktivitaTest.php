<?php

namespace Gamecon\Tests\Model\Aktivita;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Tests\Db\DbTest;

class AktivitaTest extends DbTest
{
    protected static array $initQueries = [
        <<<SQL
INSERT INTO akce_seznam(id_akce, nazev_akce, rok) VALUES (1, 'foo', 2022),(2, 'bar', 2023),(3, 'baz', 2023)
SQL,
        <<<SQL
INSERT INTO uzivatele_hodnoty SET id_uzivatele = 123, login_uzivatele = 'BylJsemTam', jmeno_uzivatele = 'BylJsem', prijmeni_uzivatele = 'Tam', email1_uzivatele = 'byl.jsem.tam@dot.com'
SQL,
        <<<SQL
INSERT INTO uzivatele_hodnoty SET id_uzivatele = 124, login_uzivatele = 'JsemNekdoJiny', jmeno_uzivatele = 'JsemNekdo', prijmeni_uzivatele = 'Jiny', email1_uzivatele = 'jsem.nekdo.jiny@dot.com'
SQL,
        <<<SQL
INSERT INTO akce_prihlaseni_log(id_akce, rocnik, id_uzivatele, zdroj_zmeny)
VALUES
    (1, 2022, 123, 'neco'), (2, 2023, 123, 'neco'),(3, 2023, 123, 'neco')
SQL,
    ];

    protected static bool $disableStrictTransTables = true;

    /**
     * @test
     */
    public function Nedostanu_zadnou_zmenu_stavu_aktivit_kdyz_nedam_aktivity() {
        self::assertNull(Aktivita::posledniZmenaStavuAktivit([]));
    }

    /**
     * @test
     */
    public function Nedostanu_zadne_posledni_zmeny_stavu_aktivit_kdyz_nedam_zname_stavy() {
        $posledniZmenyStavuAktivit = Aktivita::dejPosledniZmenyStavuAktivit([]);
        self::assertSame([], $posledniZmenyStavuAktivit->zmenyStavuAktivit());
    }

    /**
     * @test
     * @dataProvider provideZdrojOdhlaseni
     */
    public function Muzu_ziskat_nazvy_zrusenych_aktivit_uzivatele(
        int    $idUzivatele,
        string $zdrojOdhlaseni,
        int    $rocnik,
        array  $ocekavaneNazvy
    ) {
        $zruseneAktivityUzivatele = Aktivita::dejZruseneAktivityUzivatele(
            \Uzivatel::zIdUrcite($idUzivatele),
            $zdrojOdhlaseni,
            $rocnik
        );
        $nazvyZrusenychAktivit    = array_map(static fn(Aktivita $aktivita) => $aktivita->nazev(), $zruseneAktivityUzivatele);
        self::assertSame($ocekavaneNazvy, $nazvyZrusenychAktivit);
    }

    public function provideZdrojOdhlaseni() {
        return [
            'ten rok tam nebyl'                           => [123, 'neco', 2019, []],
            'ten rok byl na GC ale tohle neodhlasil'      => [123, 'jineho', 2022, []],
            'ten rok byl na GC a jednu aktivitu odhlasil' => [123, 'neco', 2022, ['foo']],
            'ten rok byl na GC a dvě aktivity odhlasil'   => [123, 'neco', 2023, ['bar', 'baz']],
            'někdo jiný'                                  => [124, 'neco', 2023, []],
        ];
    }
}
