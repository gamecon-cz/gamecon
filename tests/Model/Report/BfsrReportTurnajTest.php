<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Report;

use Gamecon\Report\BfsrReport;
use Gamecon\Tests\Db\AbstractTestDb;

/**
 * Turnaj se hraje na víc kol a týmová aktivita se hraje u víc stolů najednou.
 * Report ale počítal aktivity, takže z šestikolového turnaje o dvaceti čtyřech
 * stolech vykázal dvojku.
 */
class BfsrReportTurnajTest extends AbstractTestDb
{
    /**
     * Rozpočet platí za odehranou hru u stolu, ne za řádek v programu. Turnaj
     * o dvou bězích po třech kolech, kde u každého kola sedí čtyři až pět týmů,
     * je dvacet čtyři her - ne dvě.
     *
     * @test
     */
    public function turnajSeVykazujePoOdehranychStolech(): void
    {
        // Přihlášení v jednotlivých kolech GC2026: 1. běh 13/13/12, 2. běh 21/21/21.
        $prihlasenychPoKolech = [13, 13, 12, 21, 21, 21];
        $velikostTymu = 5;

        $stoluCelkem = 0;
        foreach ($prihlasenychPoKolech as $prihlasenych) {
            $stoluCelkem += BfsrReport::pocetOdehranychStolu($prihlasenych, $velikostTymu);
        }

        self::assertSame(
            24,
            $stoluCelkem,
            'Turnaj o šesti kolech se vykázal jinak než po odehraných stolech',
        );
    }

    /**
     * Neúplný tým u stolu pořád sedí, takže se počítá celý.
     *
     * @test
     *
     * @dataProvider neuplneTymy
     */
    public function neuplnyTymSePocitaJakoCelyStul(
        int $prihlasenych,
        int $velikostTymu,
        int $ocekavanoStolu,
    ): void {
        self::assertSame(
            $ocekavanoStolu,
            BfsrReport::pocetOdehranychStolu($prihlasenych, $velikostTymu),
        );
    }

    /**
     * @return array<string, array{int, int, int}>
     */
    public static function neuplneTymy(): array
    {
        return [
            'prázdné kolo'            => [0, 5, 0],
            'jeden hráč obsadí stůl'  => [1, 5, 1],
            'přesně jeden plný stůl'  => [5, 5, 1],
            'šestý hráč otevře druhý' => [6, 5, 2],
            'třináct hráčů po pěti'   => [13, 5, 3],
            'dvacet jedna po pěti'    => [21, 5, 5],
        ];
    }

    /**
     * Netýmová aktivita nemá stoly - jeden řádek programu je jedna hra.
     *
     * @test
     */
    public function netymovaAktivitaJeJednaHra(): void
    {
        self::assertSame(1, BfsrReport::pocetOdehranychStolu(30, null));
        self::assertSame(1, BfsrReport::pocetOdehranychStolu(0, null));
    }

    /**
     * Na stoly se dělí velikostí týmu (`team_max`), ne počtem týmů
     * (`team_kapacita`). U turnaje GC2026 jsou obě hodnoty shodou okolností 5,
     * takže záměna tam nic nerozbije - tenhle případ je má rozdílné.
     *
     * @test
     */
    public function stolySePocitajiZVelikostiTymuNeZPoctuTymu(): void
    {
        $velikostTymu = 4;
        $poctuTymu = 10;

        self::assertSame(6, BfsrReport::pocetOdehranychStolu(21, $velikostTymu));
        self::assertNotSame(
            BfsrReport::pocetOdehranychStolu(21, $poctuTymu),
            BfsrReport::pocetOdehranychStolu(21, $velikostTymu),
            'Počet stolů vyšel stejně pro velikost týmu i počet týmů, test by záměnu neodhalil',
        );
    }

    /**
     * Kapacita týmové aktivity se v rozpočtu měří v týmech, ne v hlavách -
     * u pětikolového turnaje o pěti týmech po pěti lidech je kapacita pět,
     * ne dvacet pět.
     *
     * @test
     */
    public function kapacitaTymoveAktivityJeVTymech(): void
    {
        self::assertSame(5, BfsrReport::kapacitaVJednotkachVykazu(25, 5));
    }

    /**
     * @test
     */
    public function kapacitaNetymoveAktivityZustavaVHlavach(): void
    {
        self::assertSame(30, BfsrReport::kapacitaVJednotkachVykazu(30, null));
    }

    /**
     * Vypravěč sedí u každého stolu zvlášť, takže se průměruje na stůl.
     * Turnaj GC2026 měl tři vypravěče na tři stoly v prvním běhu a pět na pět
     * ve druhém - v obou případech jeden na stůl.
     *
     * @test
     */
    public function vypraveciSePrumerujiNaStulNeNaAktivitu(): void
    {
        $kola = [
            [
                'prihlasenych' => 13,
                'vypravecu'    => 3,
            ],
            [
                'prihlasenych' => 13,
                'vypravecu'    => 3,
            ],
            [
                'prihlasenych' => 12,
                'vypravecu'    => 3,
            ],
            [
                'prihlasenych' => 21,
                'vypravecu'    => 5,
            ],
            [
                'prihlasenych' => 21,
                'vypravecu'    => 5,
            ],
            [
                'prihlasenych' => 21,
                'vypravecu'    => 5,
            ],
        ];
        $velikostTymu = 5;

        $vypravecuCelkem = 0;
        $stoluCelkem = 0;
        foreach ($kola as $kolo) {
            $vypravecuCelkem += $kolo['vypravecu'];
            $stoluCelkem += BfsrReport::pocetOdehranychStolu($kolo['prihlasenych'], $velikostTymu);
        }

        self::assertEqualsWithDelta(
            1.0,
            $vypravecuCelkem / $stoluCelkem,
            0.0001,
            'Průměrný počet vypravěčů na stůl neodpovídá jednomu vypravěči u stolu',
        );
    }
}
