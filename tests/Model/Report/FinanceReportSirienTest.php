<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Report;

use Gamecon\Tests\Db\AbstractTestDb;

class FinanceReportSirienTest extends AbstractTestDb
{
    private const SKRIPT_REPORTU = __DIR__ . '/../../../admin/scripts/zvlastni/reporty/finance-report-sirien.php';

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
