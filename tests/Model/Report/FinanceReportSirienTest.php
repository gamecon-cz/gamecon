<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Report;

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;

class FinanceReportSirienTest extends AbstractTestDb
{
    private const string SKRIPT_REPORTU = __DIR__ . '/../../../admin/scripts/zvlastni/reporty/finance-report-sirien.php';

    /**
     * Zálohy se obnovují bez uložených rutin, takže volání kterékoli z nich
     * na ostré i v preview spadne.
     *
     * @test
     */
    public function reportNevolaZadnouUlozenouSqlFunkci(): void
    {
        $zdrojReportu = $this->zdrojReportu();

        foreach (['maPravo', 'delkaAktivityJakoNasobekStandardni'] as $nazevFunkce) {
            self::assertDoesNotMatchRegularExpression(
                '~(?<![>$\w])' . preg_quote($nazevFunkce, '~') . '\s*\(~',
                $zdrojReportu,
                "Report volá SQL funkci {$nazevFunkce}, kterou databáze nemá – zálohy se obnovují bez rutin",
            );
        }
    }

    /**
     * Test výše dává smysl jen nad databází bez rutin.
     *
     * @test
     */
    public function testovaciDatabazeNemaUlozeneRutiny(): void
    {
        self::assertSame(
            '0',
            (string) dbOneCol(<<<SQL
                SELECT COUNT(*)
                FROM information_schema.routines
                WHERE routine_schema = DATABASE()
                SQL,
            ),
        );
    }

    /**
     * @test
     */
    public function vyrazyNahrazujiciSqlFunkceJsouPlatneSql(): void
    {
        $zdrojReportu = $this->zdrojReportu();

        $maPravo = $this->vyrazZeSkriptu(
            '~\$maPravoUzivatele\s*=\s*static fn[^=]*=>\s*<<<SQL\n(.*?)\n\s*SQL;~s',
            $zdrojReportu,
            'maPravoUzivatele',
        );
        $maPravo = strtr($maPravo, [
            '{$sloupecUzivatele}' => 'uzivatele_hodnoty.id_uzivatele',
            '{$pravo}'            => '1008',
        ]);

        $delkaAktivity = $this->vyrazZeSkriptu(
            '~\$delkaAktivityJakoNasobekStandardni\s*=\s*<<<SQL\n(.*?)\n\s*SQL;~s',
            $zdrojReportu,
            'delkaAktivityJakoNasobekStandardni',
        );

        self::assertContains(
            (int) dbOneCol(<<<SQL
                SELECT {$maPravo}
                FROM uzivatele_hodnoty
                LIMIT 1
                SQL,
            ),
            [0, 1],
            'Výraz nahrazující maPravo nevrací pravdivostní hodnotu',
        );

        self::assertIsNumeric(
            dbOneCol(<<<SQL
                SELECT COUNT(*)
                FROM akce_seznam
                WHERE {$delkaAktivity} IS NOT NULL
                SQL,
            ),
            'Výraz nahrazující delkaAktivityJakoNasobekStandardni neprojde v dotazu',
        );
    }

    /**
     * Report a BFSR musí přepočítat aktivitu na standardní stejně, i když netrvá celé hodiny.
     *
     * @test
     *
     * @dataProvider provideDelkyAktivit
     */
    public function delkaAktivityVReportuOdpovidaPrepoctuVPhp(string $zacatek, string $konec, float $delkaVHodinach): void
    {
        $delkaAktivity = $this->vyrazZeSkriptu(
            '~\$delkaAktivityJakoNasobekStandardni\s*=\s*<<<SQL\n(.*?)\n\s*SQL;~s',
            $this->zdrojReportu(),
            'delkaAktivityJakoNasobekStandardni',
        );

        $nasobekZReportu = dbOneCol(<<<SQL
            SELECT {$delkaAktivity}
            FROM (SELECT CAST($0 AS DATETIME) AS zacatek, CAST($1 AS DATETIME) AS konec) AS akce_seznam
            SQL,
            [
                0 => $zacatek,
                1 => $konec,
            ],
        );

        self::assertEqualsWithDelta(
            SystemoveNastaveni::getActivityStandardLengthCoefficient($delkaVHodinach),
            (float) $nasobekZReportu,
            0.0001,
        );
    }

    public static function provideDelkyAktivit(): array
    {
        return [
            'hodina'                      => ['2026-07-17 14:00:00', '2026-07-17 15:00:00', 1.0],
            'méně než hodina'             => ['2026-07-17 14:00:00', '2026-07-17 14:45:00', 0.75],
            'čtvrthodina přes hodinu'     => ['2026-07-17 14:00:00', '2026-07-17 15:15:00', 1.25],
            'standardní'                  => ['2026-07-17 14:00:00', '2026-07-17 19:00:00', 5.0],
            'čtvrthodina přes standardní' => ['2026-07-17 14:00:00', '2026-07-17 19:15:00', 5.25],
            'přes půlnoc'                 => ['2026-07-17 23:30:00', '2026-07-18 01:00:00', 1.5],
            'čtvrthodina přes 13 h'       => ['2026-07-17 08:00:00', '2026-07-17 21:15:00', 13.25],
            'patnáct hodin'               => ['2026-07-17 08:00:00', '2026-07-17 23:00:00', 15.0],
            'čtvrthodina přes 15 h'       => ['2026-07-17 08:00:00', '2026-07-17 23:15:00', 15.25],
        ];
    }

    private function zdrojReportu(): string
    {
        $zdrojReportu = file_get_contents(self::SKRIPT_REPORTU);
        self::assertNotFalse($zdrojReportu, 'Skript reportu nejde přečíst');

        return $zdrojReportu;
    }

    private function vyrazZeSkriptu(
        string $vzor,
        string $zdrojReportu,
        string $nazevVyrazu,
    ): string {
        self::assertSame(
            1,
            preg_match($vzor, $zdrojReportu, $shody),
            "Ve skriptu reportu nejde najít výraz {$nazevVyrazu}",
        );

        return $shody[1];
    }
}
