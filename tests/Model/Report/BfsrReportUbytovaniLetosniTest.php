<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Report;

use Gamecon\Report\BfsrReport;
use PHPUnit\Framework\TestCase;

/**
 * Report zná i druhy ubytování, které se dávno neprodávají (spacáky, stany,
 * chata Richor) - musí je znát, aby na jejich kódech nespadl u starších
 * ročníků. Vypisovat je ale nemá: v tabulce pak leží nulové řádky za něco,
 * co se ten rok vůbec nenabízelo.
 */
class BfsrReportUbytovaniLetosniTest extends TestCase
{
    /**
     * Sortiment GC2026: koleje a hotel, žádné spacáky ani stany.
     *
     * @test
     */
    public function vypisujiSeJenDruhyNabizeneLetos(): void
    {
        $nabizeneKody = [
            '1L_st', '2L_st', '3L_st',
            'Hd-1L-st', 'Hd-2L-st', 'Hdb-1L-st', 'Hs-1L-st', 'Hs-2L-st',
        ];

        $druhy = self::druhy($nabizeneKody);

        self::assertNotContains('spac', $druhy, 'Spacáky se 2026 nenabízely');
        self::assertNotContains('vlastni-stan', $druhy);
        self::assertNotContains('chata-richor', $druhy);
        self::assertNotContains('penzion-witch', $druhy);
        self::assertContains('1L', $druhy);
        self::assertContains('hotel-snidane-1L', $druhy);
        self::assertCount(8, $druhy);
    }

    /**
     * U staršího ročníku se naopak vypsat musí - tehdy se prodávaly.
     *
     * @test
     */
    public function starsiRocnikVypiseSveDruhy(): void
    {
        $druhy = self::druhy(['spacak_st', '1L_st', '2L_st']);

        self::assertContains('spac', $druhy);
        self::assertContains('1L', $druhy);
        self::assertNotContains('hotel-snidane-1L', $druhy, 'Hotel se 2025 nenabízel');
        self::assertCount(3, $druhy);
    }

    /**
     * Pořadí zůstává podle konstanty, ne podle toho, jak kódy přišly z databáze -
     * jinak by se řádky v tabulce mezi ročníky přeskupovaly.
     *
     * @test
     */
    public function poradiOdpovidaKonstante(): void
    {
        $druhy = self::druhy(['1L_st', 'Hs-1L-st', '3L_st', 'spacak_st']);

        self::assertSame(['spac', 'hotel-snidane-1L', '3L', '1L'], $druhy);
    }

    /**
     * Neznámý kód se musí projevit jinde (report na něm padá), ne tím, že se
     * tiše propadne do výpisu druhů.
     *
     * @test
     */
    public function neznamyKodZadnyDruhNepridava(): void
    {
        $druhy = self::druhy(['1L_st', 'neznamy_kod_xyz']);

        self::assertSame(['1L'], $druhy);
    }

    /**
     * Metoda je privátní, jako ostatní pomocníci v reportu - stejně jako
     * sousední BfsrReportUbytovaniDruhyTest sáhneme dovnitř reflexí.
     *
     * @param array<int, string> $kodyPredmetu
     *
     * @return list<string>
     */
    private static function druhy(array $kodyPredmetu): array
    {
        $metoda = (new \ReflectionClass(BfsrReport::class))->getMethod('druhyUbytovaniPodleKodu');
        $metoda->setAccessible(true);

        return $metoda->invoke(null, $kodyPredmetu);
    }
}
