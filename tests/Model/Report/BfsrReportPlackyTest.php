<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Report;

use Gamecon\Report\BfsrReport;
use PHPUnit\Framework\TestCase;

/**
 * Rozpad placek na letošní a staré se nesmí řídit ročníkem modelu: staré
 * kolekce se každý rok přeregistrují na aktuální `model_rok`, aby se daly
 * doprodat, takže podle něj je letošní úplně všechno. Letošní je jen ta jedna
 * placka z `Predmet::letosniPlacka()`.
 */
class BfsrReportPlackyTest extends TestCase
{
    private const ID_LETOSNI_PLACKY = 1846;

    /**
     * Letošní je právě ta jedna placka, kterou vrátí Predmet::letosniPlacka();
     * všechno ostatní je doprodej staré kolekce, i když má stejný `model_rok`.
     *
     * @test
     *
     * @dataProvider placky
     */
    public function stariPlackySePoznaPodleLetosnihoModelu(
        string $idPredmetu,
        bool $ocekavanoStara,
    ): void {
        self::assertSame(
            $ocekavanoStara,
            BfsrReport::jeToStaraPlacka($idPredmetu, self::ID_LETOSNI_PLACKY),
            "Placka {$idPredmetu} se zařadila špatně",
        );
    }

    /**
     * Sortiment GC2026. Všechny čtyři placky mají shodně `model_rok` 2026,
     * protože staré kolekce se přeregistrovaly kvůli doprodeji - proto se
     * podle něj rozlišit nedají.
     *
     * @return array<string, array{string, bool}>
     */
    public static function placky(): array
    {
        return [
            'letošní Verne'        => ['1846', false],
            'doprodej 2025'        => ['1847', true],
            'doprodej 2021'        => ['1903', true],
            'doprodej bez ročníku' => ['1845', true],
        ];
    }

    /**
     * Když letošní placka v sortimentu není, nemá se co označit za letošní.
     *
     * @test
     */
    public function bezLetosniPlackyJsouVsechnyStare(): void
    {
        self::assertTrue(BfsrReport::jeToStaraPlacka('1846', null));
        self::assertTrue(BfsrReport::jeToStaraPlacka('1845', null));
    }

    /**
     * Rozpad musí sedět na součet: GC2026 prodal 128 placek, z toho 116 letošní
     * kolekce (31 zdarma + 85 placených) a 12 kusů doprodeje starších kolekcí.
     *
     * @test
     */
    public function rozpadSediNaCelkovyPocet(): void
    {
        $nakupy = array_merge(
            array_fill(0, 31, ['1846', true]),   // letošní zdarma
            array_fill(0, 85, ['1846', false]),  // letošní placené
            array_fill(0, 5, ['1847', false]),   // doprodej 2025
            array_fill(0, 4, ['1903', false]),   // doprodej 2021
            array_fill(0, 3, ['1845', false]),   // doprodej stará
        );

        $letosniZdarma = $letosniPlacene = $stareZdarma = $starePlacene = 0;
        foreach ($nakupy as [$idPredmetu, $zdarma]) {
            $stara = BfsrReport::jeToStaraPlacka($idPredmetu, self::ID_LETOSNI_PLACKY);
            if ($zdarma) {
                $stara ? $stareZdarma++ : $letosniZdarma++;
            } else {
                $stara ? $starePlacene++ : $letosniPlacene++;
            }
        }

        self::assertSame(31, $letosniZdarma);
        self::assertSame(85, $letosniPlacene, 'Placené letošní byly nafouknuté o doprodej starých kolekcí');
        self::assertSame(0, $stareZdarma, 'Staré kolekce se zdarma nedávají');
        self::assertSame(12, $starePlacene, 'Doprodej starších kolekcí se nevykazoval vůbec');
        self::assertSame(128, $letosniZdarma + $letosniPlacene + $stareZdarma + $starePlacene);
    }
}
