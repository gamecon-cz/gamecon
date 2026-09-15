<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Report;

use Gamecon\Report\BfsrReport;
use PHPUnit\Framework\TestCase;

/**
 * Storna se vykazují ve dvou řádcích na sekci - 100 % za nedoražení a 50 %
 * za pozdní odhlášení. Řádky ale vznikaly nezávisle na sobě, takže sekce,
 * která má jen jeden druh storna, ten druhý řádek v reportu vůbec neměla
 * a v tabulce chyběla kolonka.
 */
class BfsrReportStornaTest extends TestCase
{
    /**
     * Sekce, která má jen 100% storno, musí dostat i nulový řádek s 50 %.
     *
     * @test
     */
    public function sekceSJednimDruhemStornaDostaneIDruhyRadek(): void
    {
        $storna100 = [
            'Vr-Storna-100-D&D/JaD turnaj' => 0.0,
        ];
        $storna50 = [];

        [$doplnene100, $doplnene50] = BfsrReport::doplnChybejiciStorna($storna100, $storna50);

        self::assertArrayHasKey('Vr-Storna-50-D&D/JaD turnaj', $doplnene50);
        self::assertSame(0.0, $doplnene50['Vr-Storna-50-D&D/JaD turnaj']);
        self::assertSame($storna100, $doplnene100);
    }

    /**
     * Platí to i opačně - sekce jen s 50% stornem dostane nulový řádek se 100 %.
     *
     * @test
     */
    public function chybejiciRadekSeDoplniIVDruhemSmeru(): void
    {
        $storna100 = [];
        $storna50 = [
            'Vr-Storna-50-doprovodný program' => 0.0,
        ];

        [$doplnene100, $doplnene50] = BfsrReport::doplnChybejiciStorna($storna100, $storna50);

        self::assertArrayHasKey('Vr-Storna-100-doprovodný program', $doplnene100);
        self::assertSame(0.0, $doplnene100['Vr-Storna-100-doprovodný program']);
        self::assertSame($storna50, $doplnene50);
    }

    /**
     * Spočítané částky se doplňováním nesmí přepsat.
     *
     * @test
     */
    public function existujiciCastkyZustanou(): void
    {
        $storna100 = [
            'Vr-Storna-100-larpy' => 600.0,
        ];
        $storna50 = [
            'Vr-Storna-50-larpy' => 4200.0,
        ];

        [$doplnene100, $doplnene50] = BfsrReport::doplnChybejiciStorna($storna100, $storna50);

        self::assertSame(600.0, $doplnene100['Vr-Storna-100-larpy']);
        self::assertSame(4200.0, $doplnene50['Vr-Storna-50-larpy']);
        self::assertCount(1, $doplnene100);
        self::assertCount(1, $doplnene50);
    }

    /**
     * Po doplnění má každá sekce právě jeden řádek v obou druzích storna,
     * takže se sloupce v tabulce dají porovnávat vedle sebe.
     *
     * @test
     */
    public function kazdaSekceMaObaRadky(): void
    {
        $storna100 = [
            'Vr-Storna-100-D&D/JaD turnaj'           => 0.0,
            'Vr-Storna-100-legendy klubu dobrodruhů' => 440.0,
            'Vr-Storna-100-larpy'                    => 600.0,
        ];
        $storna50 = [
            'Vr-Storna-50-larpy'              => 4200.0,
            'Vr-Storna-50-doprovodný program' => 0.0,
        ];

        [$doplnene100, $doplnene50] = BfsrReport::doplnChybejiciStorna($storna100, $storna50);

        $sekce100 = array_map(static fn (string $kod): string => substr($kod, strlen('Vr-Storna-100-')), array_keys($doplnene100));
        $sekce50 = array_map(static fn (string $kod): string => substr($kod, strlen('Vr-Storna-50-')), array_keys($doplnene50));
        sort($sekce100);
        sort($sekce50);

        self::assertSame($sekce100, $sekce50, 'Sekce se v obou druzích storna musí shodovat');
        self::assertCount(4, $sekce100);
    }

    /**
     * Oba druhy storna musí vyjít ve stejném pořadí, jinak se řádky v tabulce
     * nedají porovnat vedle sebe - což je celý smysl doplňování.
     *
     * @test
     */
    public function obaDruhyMajiStejnePoradiSekci(): void
    {
        $storna100 = [
            'Vr-Storna-100-larpy'          => 600.0,
            'Vr-Storna-100-D&D/JaD turnaj' => 0.0,
        ];
        $storna50 = [
            'Vr-Storna-50-doprovodný program' => 0.0,
            'Vr-Storna-50-larpy'              => 4200.0,
        ];

        [$doplnene100, $doplnene50] = BfsrReport::doplnChybejiciStorna($storna100, $storna50);

        $sekce100 = array_map(static fn (string $kod): string => substr($kod, strlen('Vr-Storna-100-')), array_keys($doplnene100));
        $sekce50 = array_map(static fn (string $kod): string => substr($kod, strlen('Vr-Storna-50-')), array_keys($doplnene50));

        self::assertSame($sekce100, $sekce50, 'Pořadí sekcí se mezi druhy storna liší');
        self::assertSame(['D&D/JaD turnaj', 'doprovodný program', 'larpy'], $sekce100);
    }
}
