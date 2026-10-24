<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Aktivita;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Cas\DateTimeCz;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Uzivatel\Finance;
use PHPUnit\Framework\TestCase;

/**
 * Aktivity začínají a končí po čtvrthodinách, délka a časy se proto nesmí
 * zaokrouhlovat na celé hodiny.
 */
class AktivitaDelkaTest extends TestCase
{
    /**
     * @dataProvider provideCasy
     */
    public function testDelkaZahrnujeNeceleHodiny(string $zacatek, string $konec, float $delka, string $delkaTextem, string $denCas): void
    {
        $aktivita = $this->aktivitaVCase($zacatek, $konec);

        self::assertSame($delka, $aktivita->delka());
        self::assertSame($delkaTextem, $aktivita->delkaTextem());
        self::assertSame($denCas, $aktivita->denCasSkutecny());
    }

    public static function provideCasy(): array
    {
        return [
            'celé hodiny'       => ['2026-07-17 14:00:00', '2026-07-17 16:00:00', 2.0, "2\u{2009}h", 'pátek 14–16'],
            'po čtvrthodinách'  => ['2026-07-17 14:15:00', '2026-07-17 15:30:00', 1.25, "1\u{2009}h 15\u{2009}min", 'pátek 14:15–15:30'],
            'kratší než hodina' => ['2026-07-17 14:00:00', '2026-07-17 14:45:00', 0.75, "45\u{2009}min", 'pátek 14–14:45'],
            'přes půlnoc'       => ['2026-07-17 23:30:00', '2026-07-18 01:00:00', 1.5, "1\u{2009}h 30\u{2009}min", 'pátek 23:30–1'],
        ];
    }

    /**
     * Pásma bonusu jsou horní meze délky, necelá hodina proto padne do vyššího pásma.
     *
     * @dataProvider provideBonusy
     */
    public function testBonusZaVedeniBereNeceleHodinyNahoru(string $konec, int $ocekavanyBonus): void
    {
        $systemoveNastaveni = $this->createMock(SystemoveNastaveni::class);
        $systemoveNastaveni->method('bonusyZaVedeniAktivity')
            ->willReturn([
                1  => 10,
                2  => 20,
                5  => 50,
                7  => 70,
                9  => 90,
                11 => 110,
                13 => 130,
            ]);

        $bonus = Finance::bonusZaAktivitu($this->aktivitaVCase('2026-07-17 08:00:00', $konec), $systemoveNastaveni);

        self::assertSame($ocekavanyBonus, $bonus);
    }

    public static function provideBonusy(): array
    {
        return [
            'čtvrthodina'         => ['2026-07-17 08:15:00', 10],
            'hodina'              => ['2026-07-17 09:00:00', 10],
            'hodina a čtvrt'      => ['2026-07-17 09:15:00', 20],
            'pět hodin'           => ['2026-07-17 13:00:00', 50],
            'pět hodin a čtvrt'   => ['2026-07-17 13:15:00', 70],
            'přes nejdelší pásmo' => ['2026-07-17 21:15:00', 0],
        ];
    }

    /**
     * Přepočet na standardní aktivitu (BFSR, finanční report): necelá hodina do vyššího pásma,
     * nad 13 h dál po dvou hodinách o půl standardní aktivity – dřív výjimka a spadlý report.
     *
     * @dataProvider provideKoeficienty
     */
    public function testKoeficientStandardniAktivity(float $delka, float $ocekavanyKoeficient): void
    {
        self::assertSame($ocekavanyKoeficient, SystemoveNastaveni::getActivityStandardLengthCoefficient($delka));
    }

    /**
     * @return array<string, array{float, float}>
     */
    public static function provideKoeficienty(): array
    {
        return [
            'čtvrthodina'           => [0.25, 0.25],
            'hodina a čtvrt'        => [1.25, 0.5],
            'pět hodin'             => [5.0, 1.0],
            'pět hodin a čtvrt'     => [5.25, 1.5],
            'třináct hodin'         => [13.0, 3.0],
            'třináct hodin a čtvrt' => [13.25, 3.5],
            'patnáct hodin'         => [15.0, 3.5],
            'patnáct hodin a čtvrt' => [15.25, 4.0],
        ];
    }

    private function aktivitaVCase(string $zacatek, string $konec): Aktivita
    {
        $aktivita = $this->createPartialMock(Aktivita::class, ['zacatek', 'konec', 'nedavaBonus']);
        $aktivita->method('nedavaBonus')->willReturn(false);
        $aktivita->method('zacatek')->willReturn(new DateTimeCz($zacatek));
        $aktivita->method('konec')->willReturn(new DateTimeCz($konec));

        return $aktivita;
    }
}
