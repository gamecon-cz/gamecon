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
}
