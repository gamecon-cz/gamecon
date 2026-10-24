<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Aktivita;

use Gamecon\Aktivita\Program;
use Gamecon\Cas\DateTimeCz;
use PHPUnit\Framework\TestCase;

/**
 * Časy aktivit v editoru jsou po čtvrthodinách; backend nesmí uložit nic jiného,
 * co by tabulka programu neuměla přesně zobrazit.
 */
class ProgramCasyTest extends TestCase
{
    /**
     * @dataProvider provideCasyFormulare
     */
    public function testMinutyZCasuFormulare(mixed $cas, ?int $ocekavaneMinuty): void
    {
        self::assertSame($ocekavaneMinuty, Program::minutyZCasuFormulare($cas));
    }

    /**
     * @return array<string, array{mixed, int|null}>
     */
    public static function provideCasyFormulare(): array
    {
        return [
            'čtvrthodina'              => ['14:15', 855],
            'celá hodina s minutami'   => ['8:00', 480],
            'starý tvar jen s hodinou' => ['14', 840],
            'hodina jako číslo'        => [14, 840],
            'půlnoc na začátku dne'    => ['0:00', 0],
            'půlnoc na konci dne'      => ['24:00', 1440],
            'starý tvar půlnoci'       => ['24', 1440],
            'mimo čtvrthodiny'         => ['14:10', null],
            'za půlnocí konce dne'     => ['24:15', null],
            'víc než 59 minut'         => ['14:75', null],
            'prázdný'                  => ['', null],
            'nesmysl'                  => ['abc', null],
            'nic'                      => [null, null],
        ];
    }

    /**
     * @dataProvider provideCasyVEditoru
     */
    public function testEditorPredvyberCasu(string $zacatek, string $konec, int $ocekavanyZacatek, int $ocekavanyKonec): void
    {
        self::assertSame($ocekavanyZacatek, Program::minutaZacatkuVEditoru(new DateTimeCz($zacatek)));
        self::assertSame($ocekavanyKonec, Program::minutaKonceVEditoru(new DateTimeCz($konec)));
    }

    /**
     * @return array<string, array{string, string, int, int}>
     */
    public static function provideCasyVEditoru(): array
    {
        return [
            'po čtvrthodinách'      => ['2026-07-17 14:15:00', '2026-07-17 15:45:00', 855, 945],
            'konec o půlnoci'       => ['2026-07-17 23:00:00', '2026-07-18 00:00:00', 1380, 0],
            'začátek o půlnoci'     => ['2026-07-18 00:00:00', '2026-07-18 03:00:00', 0, 180],
            'mimo čtvrthodiny dolů' => ['2026-07-17 14:10:00', '2026-07-17 16:50:00', 840, 1005],
        ];
    }

    public function testFormatujCasFormulare(): void
    {
        self::assertSame('14:15', Program::formatujCasFormulare(855));
        self::assertSame('0:00', Program::formatujCasFormulare(0));
        self::assertSame('24:00', Program::formatujCasFormulare(1440));
    }
}
