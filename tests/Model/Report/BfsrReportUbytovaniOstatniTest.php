<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Report;

use Gamecon\Report\BfsrReport;
use PHPUnit\Framework\TestCase;

/**
 * Hotelové druhy mají v konstantě i holou předponu bez třídy pokoje, která
 * zachytí třídu, kterou report ještě nezná - jinak by na jejím kódu spadl.
 * Takový řádek se ale nesmí jmenovat jako plnohodnotná kategorie, protože pak
 * není poznat, že jde o zbytkový koš.
 */
class BfsrReportUbytovaniOstatniTest extends TestCase
{
    /**
     * @test
     *
     * @dataProvider zbytkoveDruhy
     */
    public function zbytkovyDruhMaVKoduIPopisuOstatni(
        string $kodPredmetu,
        string $ocekavanyDruh,
        string $ocekavanyPopis,
    ): void {
        $druhy = (new \ReflectionClass(BfsrReport::class))->getConstant('DRUHY_UBYTOVANI');

        self::assertArrayHasKey($ocekavanyDruh, $druhy, 'Zbytkový druh musí v konstantě být');
        self::assertSame($ocekavanyPopis, $druhy[$ocekavanyDruh][1]);
        self::assertSame($ocekavanyDruh, self::zarad($kodPredmetu, $druhy));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function zbytkoveDruhy(): array
    {
        return [
            'neznámá třída deluxe'     => ['Hd-3L-ct', 'hotel-deluxe-ostatni', 'hotel deluxe ostatní'],
            'neznámá třída se snídaní' => ['Hs-4L-pa', 'hotel-snidane-ostatni', 'hotel se snídaní ostatní'],
            'neznámá třída dvojbuňky'  => ['Hdb-9L-st', 'hotel-deluxe-2b-ostatni', 'hotel deluxe dvojbuňka ostatní'],
            'hotel bez třídy pokoje'   => ['Hd-ct', 'hotel-deluxe-ostatni', 'hotel deluxe ostatní'],
        ];
    }

    /**
     * Známé třídy pokoje do zbytkového koše spadnout nesmí.
     *
     * @test
     *
     * @dataProvider znameTridy
     */
    public function znamaTridaSeDoOstatnichNepropadne(
        string $kodPredmetu,
        string $ocekavanyDruh,
    ): void {
        $druhy = (new \ReflectionClass(BfsrReport::class))->getConstant('DRUHY_UBYTOVANI');

        self::assertSame($ocekavanyDruh, self::zarad($kodPredmetu, $druhy));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function znameTridy(): array
    {
        return [
            'deluxe 1L'     => ['Hd-1L-ct', 'hotel-deluxe-1L'],
            'deluxe 2L'     => ['Hd-2L-ct', 'hotel-deluxe-2L'],
            'dvojbuňka 1L'  => ['Hdb-1L-ct', 'hotel-deluxe-2b-1L'],
            'se snídaní 1L' => ['Hs-1L-ct', 'hotel-snidane-1L'],
            'se snídaní 2L' => ['Hs-2L-ct', 'hotel-snidane-2L'],
            'kolej 1L'      => ['1L_ct', '1L'],
        ];
    }

    /**
     * Zbytkový druh nesmí existovat u kolejí ani u zrušených druhů - ty mají
     * pevný sortiment, takže tam žádná neznámá varianta vzniknout nemůže.
     *
     * @test
     */
    public function ostatniMajiJenHotely(): void
    {
        $druhy = (new \ReflectionClass(BfsrReport::class))->getConstant('DRUHY_UBYTOVANI');

        $ostatni = array_filter(array_keys($druhy), static fn (string $druh): bool => str_ends_with($druh, '-ostatni'));
        sort($ostatni);

        self::assertSame(
            ['hotel-deluxe-2b-ostatni', 'hotel-deluxe-ostatni', 'hotel-snidane-ostatni'],
            $ostatni,
        );
    }

    /**
     * Zrcadlí zařazování reportu: první sedící předpona vyhrává.
     *
     * @param array<string, array{string, string}> $druhy
     */
    private static function zarad(string $kodPredmetu, array $druhy): ?string
    {
        foreach ($druhy as $druh => [$predpona]) {
            if (str_starts_with($kodPredmetu, $predpona)) {
                return $druh;
            }
        }

        return null;
    }
}
