<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Report;

use Gamecon\Report\BfsrReport;
use Gamecon\Tests\Db\AbstractTestDb;

/**
 * Hotel se od stěhování prodává ve dvou třídách pokojů, ale report je slil do
 * jednoho řádku na kategorii, takže 1L a 2L hotel nešlo v rozpočtu rozlišit.
 */
class BfsrReportUbytovaniDruhyTest extends AbstractTestDb
{
    /**
     * @test
     *
     * @dataProvider kodyUbytovani
     */
    public function kodUbytovaniSpadneDoSpravnehoDruhu(
        string $kodPredmetu,
        string $ocekavanyDruh,
    ): void {
        self::assertSame(
            $ocekavanyDruh,
            self::zarad($kodPredmetu),
        );
    }

    /**
     * Zařadí kód stejně jako report - první předpona, na kterou kód sedí.
     * Test jde přes konstantu, aby ověřoval i pořadí předpon, na kterém to celé stojí.
     */
    private static function zarad(string $kodPredmetu): ?string
    {
        $druhyUbytovani = (new \ReflectionClass(BfsrReport::class))->getConstant('DRUHY_UBYTOVANI');
        foreach ($druhyUbytovani as $druh => [$predpona]) {
            if (str_starts_with($kodPredmetu, $predpona)) {
                return $druh;
            }
        }

        return null;
    }

    /**
     * Sortiment 2026 - hotel má 1L i 2L pokoje ve všech třech třídách.
     *
     * @return array<string, array{string, string}>
     */
    public static function kodyUbytovani(): array
    {
        return [
            'hotel deluxe 1L'           => ['Hd-1L-ct', 'hotel-deluxe-1L'],
            'hotel deluxe 2L'           => ['Hd-2L-ct', 'hotel-deluxe-2L'],
            'hotel deluxe dvojbuňka 1L' => ['Hdb-1L-ct', 'hotel-deluxe-2b-1L'],
            'hotel se snídaní 1L'       => ['Hs-1L-ct', 'hotel-snidane-1L'],
            'hotel se snídaní 2L'       => ['Hs-2L-ct', 'hotel-snidane-2L'],
            'kolej 1L'                  => ['1L_ct', '1L'],
            'kolej 2L'                  => ['2L_ct', '2L'],
            'kolej 3L'                  => ['3L_ct', '3L'],
            'spacák'                    => ['spacak_ct', 'spac'],
            'vlastní stan'              => ['vlastni_stan_ct', 'vlastni-stan'],
            'chata Richor'              => ['4L_chataRichor_ct', 'chata-richor'],
            'penzion Witch'             => ['2_4L_penzionWitch_ct', 'penzion-witch'],
        ];
    }

    /**
     * Dvojbuňka musí zůstat oddělená od obyčejného deluxe - obě začínají na `Hd`.
     *
     * @test
     */
    public function dvojbunkaSeNeschovaDoDeluxe(): void
    {
        self::assertNotSame(
            self::zarad('Hd-1L-ct'),
            self::zarad('Hdb-1L-ct'),
        );
    }

    /**
     * Každá třída hotelového pokoje má v reportu vlastní řádek, jinak se
     * 1L a 2L hotel sečtou do jednoho čísla.
     *
     * @test
     */
    public function hotelMaVlastniRadekProKazdouTriduPokoje(): void
    {
        $druhy = array_keys(
            (new \ReflectionClass(BfsrReport::class))->getConstant('DRUHY_UBYTOVANI'),
        );

        foreach (['hotel-deluxe-1L', 'hotel-deluxe-2L', 'hotel-snidane-1L', 'hotel-snidane-2L'] as $ocekavanyDruh) {
            self::assertContains(
                $ocekavanyDruh,
                $druhy,
                "Report nemá vlastní řádek pro '{$ocekavanyDruh}', takže třídy pokojů slévá",
            );
        }
    }
}
