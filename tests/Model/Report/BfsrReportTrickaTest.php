<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Report;

use Gamecon\Report\BfsrReport;
use Gamecon\Shop\TypPredmetu;
use Gamecon\Uzivatel\Dto\PolozkaProBfgr;
use PHPUnit\Framework\TestCase;

/**
 * Report hlásil nula prodaných triček: čítač placených triček se nikdy
 * nezvyšoval a tričko zdarma v jiné barvě než červené či modré navíc
 * propadalo ze své větve dál. Tílka měla stejnou větev správně.
 */
class BfsrReportTrickaTest extends TestCase
{
    /**
     * @test
     *
     * @dataProvider svrsky
     */
    public function svrsekSpadneDoJedineKategorie(
        string $nazev,
        string $kodPredmetu,
        float $castka,
        float $sleva,
        string $ocekavanaKategorie,
    ): void {
        self::assertSame(
            $ocekavanaKategorie,
            BfsrReport::kategorieSvrsku(
                self::svrsek($nazev, $kodPredmetu, $castka, $sleva),
            ),
        );
    }

    /**
     * @return array<string, array{string, string, float, float, string}>
     */
    public static function svrsky(): array
    {
        $ucastnicke = 'tricko_panske_ucastnicke_L_2026';
        $orgovske = 'tricko_panske_organizatorske_L_2026';
        $vypravecske = 'tricko_panske_vypravecske_L_2026';
        $tilko = 'tricko_tilko_ucastnicke_2026';

        return [
            'placené účastnické' => ['Tričko účastnické L', $ucastnicke, 250.0, 0.0, BfsrReport::SVRSEK_PLACENY],
            'zdarma červené'     => ['Tričko červené L', $orgovske, 0.0, 250.0, BfsrReport::SVRSEK_ZDARMA],
            'zdarma modré'       => ['Tričko modré L', $vypravecske, 0.0, 250.0, BfsrReport::SVRSEK_ZDARMA],
            'zdarma jiná barva'  => ['Tričko účastnické L', $ucastnicke, 0.0, 250.0, BfsrReport::SVRSEK_ZDARMA],
            'se slevou'          => ['Tričko účastnické L', $ucastnicke, 100.0, 150.0, BfsrReport::SVRSEK_SE_SLEVOU],
            'placené tílko'      => ['Tričko/tílko účastnické 2026', $tilko, 250.0, 0.0, BfsrReport::SVRSEK_PLACENY],
        ];
    }

    /**
     * Hodnost se bere z kódu, ne z barvy v názvu. V roce 2009 a 2010 byla orgovská
     * trička oranžová — v datech je jich 14 a podle názvu by spadla mezi účastnická.
     *
     * @test
     */
    public function orgovskeTrickoSePoznaIKdyzNeniCervene(): void
    {
        $oranzoveOrgovske = self::svrsek('Tričko oranžové pánské', 'tricko_panske_organizatorske_L_2009', 0.0, 250.0);

        self::assertTrue(
            \Gamecon\Shop\Predmet::jeToOrganizatorske($oranzoveOrgovske),
            'Orgovské tričko se musí poznat podle kódu, i když se barvou vymyká',
        );
        self::assertSame(BfsrReport::SVRSEK_ZDARMA, BfsrReport::kategorieSvrsku($oranzoveOrgovske));
    }

    /**
     * Tohle je ta chyba: tričko zdarma v jiné barvě než červené či modré
     * se započítalo do zdarma a pak propadlo i mezi placená.
     *
     * @test
     */
    public function trickoZdarmaVJineBarveNeniPlacene(): void
    {
        self::assertSame(
            BfsrReport::SVRSEK_ZDARMA,
            BfsrReport::kategorieSvrsku(
                self::svrsek('Tričko účastnické L', 'tricko_panske_ucastnicke_L_2026', 0.0, 250.0),
            ),
        );
    }

    /**
     * Sortiment GC2026: 386 svršků = 123 zdarma + 67 se slevou + 196 placených.
     * Report hlásil nula placených. Kategorie se musí sečíst na celkový počet,
     * jinak se některý svršek ztratil nebo se počítá dvakrát.
     *
     * @test
     */
    public function kategorieSeSectouNaCelkovyPocet(): void
    {
        $svrsky = array_merge(
            array_fill(0, 123, self::svrsek('Tričko účastnické L', 'tricko_panske_ucastnicke_L_2026', 0.0, 250.0)),
            array_fill(0, 67, self::svrsek('Tričko modré L', 'tricko_panske_vypravecske_L_2026', 250.0, 0.0)),
            array_fill(0, 196, self::svrsek('Tričko účastnické L', 'tricko_panske_ucastnicke_L_2026', 250.0, 0.0)),
        );

        $pocty = [
            BfsrReport::SVRSEK_ZDARMA    => 0,
            BfsrReport::SVRSEK_SE_SLEVOU => 0,
            BfsrReport::SVRSEK_PLACENY   => 0,
        ];
        foreach ($svrsky as $svrsek) {
            ++$pocty[BfsrReport::kategorieSvrsku($svrsek)];
        }

        self::assertSame(123, $pocty[BfsrReport::SVRSEK_ZDARMA]);
        self::assertSame(67, $pocty[BfsrReport::SVRSEK_SE_SLEVOU]);
        self::assertSame(196, $pocty[BfsrReport::SVRSEK_PLACENY], 'Report hlásil nula prodaných triček');
        self::assertSame(386, array_sum($pocty));
    }

    private static function svrsek(
        string $nazev,
        string $kodPredmetu,
        float $castka,
        float $sleva,
    ): PolozkaProBfgr {
        return new PolozkaProBfgr(
            nazev: $nazev,
            pocet: '1',
            castka: $castka,
            sleva: $sleva,
            typ: TypPredmetu::TRICKO,
            kodPredmetu: $kodPredmetu,
            idPredmetu: '1',
        );
    }
}
