<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Aktivita;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\SqlStruktura\AkceSeznamSqlStruktura as Sql;
use Gamecon\Tests\Db\AbstractTestDb;

class AktivitaEditorChybyTest extends AbstractTestDb
{
    private static function zavolejEditorChyby(array $a): array
    {
        $reflection = new \ReflectionMethod(Aktivita::class, 'editorChyby');

        return $reflection->invoke(null, $a);
    }

    private static function zakladniData(int $zacatek, int $konec): array
    {
        return [
            'den'          => '2026-07-15',
            Sql::ZACATEK   => $zacatek,
            Sql::KONEC     => $konec,
            Sql::ID_AKCE   => 0,
            Sql::URL_AKCE  => 'test-aktivita-' . $zacatek . '-' . $konec,
            Sql::PATRI_POD => null,
        ];
    }

    public function testKonecPredZacatkemJeChyba(): void
    {
        $chyby = self::zavolejEditorChyby(self::zakladniData(18, 14));

        $this->assertNotEmpty($chyby, 'Měla by být chyba když konec je před začátkem');
        $this->assertStringContainsString(
            'Konec aktivity musí být po jejím začátku',
            implode('; ', $chyby),
        );
    }

    public function testValidniCasNeniChyba(): void
    {
        $chyby = self::zavolejEditorChyby(self::zakladniData(13, 14));

        $casoveChyby = array_filter($chyby, static function (string $chyba): bool {
            return str_contains($chyba, 'začátku');
        });
        $this->assertEmpty($casoveChyby, 'Neměla by být chyba pro validní čas 13-14');
    }

    public function testPrechodPresPulnocJeValidni(): void
    {
        $chyby = self::zavolejEditorChyby(self::zakladniData(22, 2));

        $casoveChyby = array_filter($chyby, static function (string $chyba): bool {
            return str_contains($chyba, 'začátku');
        });
        $this->assertEmpty($casoveChyby, 'Neměla by být chyba pro přechod přes půlnoc 22-2');
    }

    public function testPopisKratkyValidniDelkaNeniChyba(): void
    {
        $data = self::zakladniData(13, 14);
        $data[Sql::POPIS_KRATKY] = str_repeat('a', Aktivita::LIMIT_POPIS_KRATKY); // exactly 180 chars

        $chyby = self::zavolejEditorChyby($data);

        $popisChyby = array_filter($chyby, static function (string $chyba): bool {
            return str_contains($chyba, 'Krátký popis překračuje maximální povolenou délku');
        });
        $this->assertEmpty($popisChyby, 'Neměla by být chyba pro popisek s délkou na hranici limitu');
    }

    public function testPopisKratkyPrekrocujeLimitJeChyba(): void
    {
        $data = self::zakladniData(13, 14);
        $data[Sql::POPIS_KRATKY] = str_repeat('a', Aktivita::LIMIT_POPIS_KRATKY + 1); // 181 chars

        $chyby = self::zavolejEditorChyby($data);

        $this->assertNotEmpty($chyby, 'Měla by být chyba pro popisek přesahující limit');
        $this->assertStringContainsString(
            'Krátký popis překračuje maximální povolenou délku',
            implode('; ', $chyby),
        );
    }

    public function testPopisKratkyPrekrocujeLimitZpusobiChybuAPrerusiUkladani(): void
    {
        $data = self::zakladniData(13, 14);
        $data[Sql::NAZEV_AKCE] = 'Aktivitka s moc dlouhym textem ' . uniqid();
        $data[Sql::URL_AKCE] = 'aktivitka-s-moc-dlouhym-textem-' . uniqid();
        $data[Sql::POPIS_KRATKY] = str_repeat('a', Aktivita::LIMIT_POPIS_KRATKY + 1);
        $data[Sql::POPIS] = 'Popis';

        $_POST[Aktivita::POST_KLIC] = $data;

        $pocetAktivitPred = (int) dbOneCol('SELECT COUNT(*) FROM akce_seznam');

        try {
            Aktivita::editorZpracuj(false);
            $this->fail('Očekávána exception \Chyba kvůli příliš dlouhému krátkému popisu.');
        } catch (\Chyba $e) {
            $this->assertStringContainsString('Krátký popis překračuje maximální povolenou délku', $e->getMessage());
        } finally {
            unset($_POST[Aktivita::POST_KLIC]);
        }

        $pocetAktivitPo = (int) dbOneCol('SELECT COUNT(*) FROM akce_seznam');
        $this->assertSame($pocetAktivitPred, $pocetAktivitPo, 'Žádná nová aktivita nesměla být uložena do DB.');
    }
}
