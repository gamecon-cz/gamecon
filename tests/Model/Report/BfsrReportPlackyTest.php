<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Report;

use Gamecon\Report\BfsrReport;
use PHPUnit\Framework\TestCase;

/**
 * Rozpad placek na letošní a staré se nesmí řídit ročníkem modelu: staré
 * kolekce se každý rok přeregistrují na aktuální `model_rok`, aby se daly
 * doprodat, takže podle něj je letošní úplně všechno. Letošní je jen ta jedna
 * placka, kterou jmenuje letošní pravidlo placky zdarma.
 */
class BfsrReportPlackyTest extends TestCase
{
    private const KOD_LETOSNI_PLACKY = 'placka_2026_verne';

    /**
     * Letošní je právě ta jedna placka, kterou jmenuje pravidlo placky zdarma;
     * všechno ostatní je doprodej staré kolekce, i když má stejný `model_rok`.
     *
     * @test
     *
     * @dataProvider placky
     */
    public function stariPlackySePoznaPodleLetosnihoModelu(
        string $kodPredmetu,
        bool $ocekavanoStara,
    ): void {
        self::assertSame(
            $ocekavanoStara,
            BfsrReport::jeToStaraPlacka($kodPredmetu, self::KOD_LETOSNI_PLACKY),
            "Placka {$kodPredmetu} se zařadila špatně",
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
            'letošní Verne'        => ['placka_2026_verne', false],
            'doprodej 2025'        => ['placka_2025_cas', true],
            'doprodej 2021'        => ['placka_2021', true],
            'doprodej bez ročníku' => ['placka_old', true],
        ];
    }

    /**
     * Když letošní placka v sortimentu není, nemá se co označit za letošní.
     *
     * @test
     */
    public function bezLetosniPlackyJsouVsechnyStare(): void
    {
        self::assertTrue(BfsrReport::jeToStaraPlacka('placka_2026_verne', null));
        self::assertTrue(BfsrReport::jeToStaraPlacka('placka_old', null));
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
            array_fill(0, 31, ['placka_2026_verne', true]),   // letošní zdarma
            array_fill(0, 85, ['placka_2026_verne', false]),  // letošní placené
            array_fill(0, 5, ['placka_2025_cas', false]),     // doprodej 2025
            array_fill(0, 4, ['placka_2021', false]),         // doprodej 2021
            array_fill(0, 3, ['placka_old', false]),          // doprodej stará
        );

        $letosniZdarma = $letosniPlacene = $stareZdarma = $starePlacene = 0;
        foreach ($nakupy as [$kodPredmetu, $zdarma]) {
            $stara = BfsrReport::jeToStaraPlacka($kodPredmetu, self::KOD_LETOSNI_PLACKY);
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
