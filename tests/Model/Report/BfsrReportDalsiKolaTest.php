<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Report;

use Gamecon\Report\BfsrReport;
use PHPUnit\Framework\TestCase;

/**
 * Další kolo turnaje se zakládá bez kapacity a bez velikosti týmu - postupuje
 * do něj celá sestava z prvního kola, takže se nic nezadává znovu. Report proto
 * musí obojí převzít z prvního kola téhož turnaje, jinak takové kolo vykáže
 * nulovou kapacitu a jediný stůl.
 */
class BfsrReportDalsiKolaTest extends TestCase
{
    /**
     * Sestava z prvního kola pokračuje dál, takže i kapacita platí dál.
     *
     * @test
     */
    public function dalsiKoloBezKapacityJiPrevezmeZPrvnihoKola(): void
    {
        self::assertSame(
            5,
            BfsrReport::kapacitaDalsihoKola(kapacitaKola: 0, kapacitaPrvnihoKola: 5),
        );
    }

    /**
     * Vlastní kapacitu si kolo nechá - přebírá se jen když chybí.
     *
     * @test
     */
    public function dalsiKoloSVlastniKapacitouSiJiNechava(): void
    {
        self::assertSame(
            3,
            BfsrReport::kapacitaDalsihoKola(kapacitaKola: 3, kapacitaPrvnihoKola: 5),
        );
    }

    /**
     * Když první kolo kapacitu taky nemá, není co převzít.
     *
     * @test
     */
    public function bezKapacityVPrvnimKoleZustavaNula(): void
    {
        self::assertSame(
            0,
            BfsrReport::kapacitaDalsihoKola(kapacitaKola: 0, kapacitaPrvnihoKola: 0),
        );
    }

    /**
     * Totéž pro velikost týmu - bez ní nejde spočítat počet stolů.
     *
     * @test
     *
     * @dataProvider velikostiTymu
     */
    public function velikostTymuSePrebiraZPrvnihoKola(
        ?int $velikostKola,
        ?int $velikostPrvnihoKola,
        ?int $ocekavano,
    ): void {
        self::assertSame(
            $ocekavano,
            BfsrReport::velikostTymuDalsihoKola($velikostKola, $velikostPrvnihoKola),
        );
    }

    /**
     * @return array<string, array{int|null, int|null, int|null}>
     */
    public static function velikostiTymu(): array
    {
        return [
            'kolo bez velikosti týmu'  => [0, 5, 5],
            'kolo s NULL'              => [null, 5, 5],
            'kolo s vlastní velikostí' => [4, 5, 4],
            'ani první kolo nemá'      => [0, 0, null],
            'obojí NULL'               => [null, null, null],
        ];
    }

    /**
     * Turnaj může mít víc prvních kol naráz a nemusí mít všechna stejnou kapacitu
     * (LKD 2024/2025 má první kola po 4 i 5 lidech). Přebírá se maximum, aby
     * výsledek nezávisel na pořadí aktivit.
     *
     * @test
     */
    public function zVicPrvnichKolSePrebiraMaximum(): void
    {
        $prvniKola = [4, 5, 4];

        self::assertSame(
            5,
            BfsrReport::kapacitaDalsihoKola(kapacitaKola: 0, kapacitaPrvnihoKola: max($prvniKola)),
        );
    }

    /**
     * LKD GC2026: první kola po pěti lidech u stolu, druhá kola bez týmových dat.
     * Do druhého kola turnaje 72 postoupilo 13 lidí, což jsou tři stoly - ne jeden,
     * jak vycházelo, dokud se velikost týmu nepřebírala.
     *
     * @test
     */
    public function druheKoloLkdSePocitaPoStolech(): void
    {
        $velikostTymu = BfsrReport::velikostTymuDalsihoKola(0, 5);

        self::assertSame(3, BfsrReport::pocetOdehranychStolu(13, $velikostTymu));
        self::assertSame(2, BfsrReport::pocetOdehranychStolu(8, $velikostTymu));
        self::assertSame(2, BfsrReport::pocetOdehranychStolu(6, $velikostTymu));
    }
}
