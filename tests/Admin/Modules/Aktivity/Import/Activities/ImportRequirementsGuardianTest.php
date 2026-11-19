<?php

declare(strict_types=1);

namespace Gamecon\Tests\Admin\Modules\Aktivity\Import\Activities;

use Gamecon\Admin\Modules\Aktivity\Export\ExportAktivitSloupce;
use Gamecon\Admin\Modules\Aktivity\Import\Activities\ImportObjectsContainer;
use Gamecon\Admin\Modules\Aktivity\Import\Activities\ImportRequirementsGuardian;
use Gamecon\Admin\Modules\Aktivity\Import\Activities\ImportStepResult;
use Gamecon\Admin\Modules\Aktivity\Import\Activities\ImportUsersCache;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Tests\Db\AbstractTestDb;

/**
 * Programová linie importu připadne i novým aktivitám bez vyplněné linie, takže neaktivní být nesmí.
 */
class ImportRequirementsGuardianTest extends AbstractTestDb
{
    private const int ID_AKTIVITY_BEZ_TYPU = 7221;
    private const int ID_AKTIVITY_LARP = 7222;

    protected static bool $disableStrictTransTables = true;

    /**
     * @return list<string>
     */
    protected static function getSetUpBeforeClassInitQueries(): array
    {
        $rok = (string) ROCNIK;
        $bezTypu = TypAktivity::SYSTEMOVA;
        $larp = TypAktivity::LARP;

        return [
            <<<SQL
INSERT INTO akce_seznam(id_akce, nazev_akce, url_akce, typ, rok, stav, patri_pod, kapacita, kapacita_f, kapacita_m, teamova)
VALUES
    (7221, 'Aktivita bez typu', 'aktivita-bez-typu-import-test', {$bezTypu}, {$rok}, 1, NULL, 5, 0, 0, 0),
    (7222, 'Larp', 'larp-import-test', {$larp}, {$rok}, 1, NULL, 5, 0, 0, 0)
SQL,
        ];
    }

    public function testLinieDleNazvu(): void
    {
        $vysledek = $this->zkontrolujRadky([[
            ExportAktivitSloupce::PROGRAMOVA_LINIE => 'larpy',
        ]]);

        self::assertFalse($vysledek->isError(), $vysledek->getError());
        self::assertSame(TypAktivity::LARP, $vysledek->getSuccess()->id());
    }

    /**
     * @dataProvider provideRadkySLiniiBezTypu
     *
     * @param list<array<string, string>> $radky
     */
    public function testNeaktivniLinieBezTypuNejdeImportovat(array $radky): void
    {
        $vysledek = $this->zkontrolujRadky($radky);

        self::assertTrue($vysledek->isError());
        self::assertSame(
            "Programová linie '(bez typu – organizační)' se už nepoužívá, importovat do ní nejde.",
            $vysledek->getError(),
        );
    }

    /**
     * @return array<string, array{list<array<string, string>>}>
     */
    public static function provideRadkySLiniiBezTypu(): array
    {
        return [
            'název' => [[[
                ExportAktivitSloupce::PROGRAMOVA_LINIE => '(bez typu – organizační)',
            ]]],
            'adresa' => [[[
                ExportAktivitSloupce::PROGRAMOVA_LINIE => 'organizacni',
            ]]],
            'existující aktivita bez typu' => [[[
                ExportAktivitSloupce::ID_AKTIVITY => (string) self::ID_AKTIVITY_BEZ_TYPU,
            ]]],
            'existující aktivita a nová bez linie' => [[
                [
                    ExportAktivitSloupce::ID_AKTIVITY => (string) self::ID_AKTIVITY_BEZ_TYPU,
                ],
                [
                    ExportAktivitSloupce::NAZEV => 'Nová aktivita',
                ],
            ]],
        ];
    }

    public function testIdLinieBezTypuSeBereJakoNevyplnenaLinie(): void
    {
        // '0' je pro import prázdná hodnota, linie se tedy nenajde a soubor neprojde ještě před kontrolou aktivity
        $vysledek = $this->zkontrolujRadky([[
            ExportAktivitSloupce::PROGRAMOVA_LINIE => (string) TypAktivity::SYSTEMOVA,
        ]]);

        self::assertTrue($vysledek->isError());
        self::assertStringStartsWith('V importovaném souboru chybí programová linie', $vysledek->getError());
    }

    public function testSouborSDvemaLiniemiNejdeImportovatBezOhleduNaPoradiRadku(): void
    {
        $larp = [
            ExportAktivitSloupce::ID_AKTIVITY => (string) self::ID_AKTIVITY_LARP,
        ];
        $bezTypu = [
            ExportAktivitSloupce::ID_AKTIVITY => (string) self::ID_AKTIVITY_BEZ_TYPU,
        ];

        foreach ([[$larp, $bezTypu], [$bezTypu, $larp]] as $radky) {
            $vysledek = $this->zkontrolujRadky($radky);

            self::assertTrue($vysledek->isError());
            self::assertStringStartsWith('Importovat lze pouze jednu programovou linii.', $vysledek->getError());
        }
    }

    /**
     * @param list<array<string, string>> $radky
     */
    private function zkontrolujRadky(array $radky): ImportStepResult
    {
        return (new ImportRequirementsGuardian(new ImportObjectsContainer(new ImportUsersCache())))
            ->guardSingleProgramLineOnly($radky, 'test.xlsx');
    }
}
