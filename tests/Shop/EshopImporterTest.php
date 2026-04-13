<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

use Gamecon\Shop\EshopImporter;
use Gamecon\Shop\StavPredmetu;
use Gamecon\Tests\Db\AbstractTestDb;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XLSXWriter;

class EshopImporterTest extends AbstractTestDb
{
    protected static bool $disableStrictTransTables = true;

    protected static function keepTestClassDbChangesInTransaction(): bool
    {
        return false;
    }

    protected static function resetDbAfterClass(): bool
    {
        return true;
    }

    protected static function keepSingleTestMethodDbChangesInTransaction(): bool
    {
        return false;
    }

    protected static function resetDbAfterSingleTestMethod(): bool
    {
        return true;
    }

    private static array $hlavicka = [
        'nazev',
        'kod_predmetu',
        'cena_aktualni',
        'stav',
        'nabizet_do',
        'kusu_vyrobeno',
        'tag',
        'ubytovani_den',
        'popis',
        'vedlejsi',
        'snidane_v_cene',
    ];

    private function createXlsxSoubor(array $radky): string
    {
        $soubor = tempnam(sys_get_temp_dir(), 'eshop_import_test_') . '.xlsx';
        $writer = new XLSXWriter();
        $writer->openToFile($soubor);
        $writer->addRow(Row::fromValues(self::$hlavicka));
        foreach ($radky as $radek) {
            $writer->addRow(Row::fromValues($radek));
        }
        $writer->close();

        return $soubor;
    }

    private function defaultniRadek(array $prepisVrednosti = []): array
    {
        $radek = [
            'nazev'          => 'Testovací předmět',
            'kod_predmetu'   => 'TEST_KOD',
            'cena_aktualni'  => '199.00',
            'stav'           => StavPredmetu::VEREJNY,
            'nabizet_do'     => '2025-12-31',
            'kusu_vyrobeno'  => 100,
            'tag'            => 'predmet',
            'ubytovani_den'  => '',
            'popis'          => 'Popis předmětu',
            'vedlejsi'       => 0,
            'snidane_v_cene' => 0,
        ];

        return array_merge($radek, $prepisVrednosti);
    }

    /**
     * @test
     */
    public function importVloziNovePolozky(): void
    {
        $soubor = $this->createXlsxSoubor([
            $this->defaultniRadek([
                'kod_predmetu' => 'POLOZKA_A',
                'nazev'        => 'Předmět A',
            ]),
            $this->defaultniRadek([
                'kod_predmetu' => 'POLOZKA_B',
                'nazev'        => 'Předmět B',
            ]),
        ]);

        $importer = new EshopImporter($soubor);
        $vysledek = $importer->importuj();

        self::assertSame(2, $vysledek->pocetNovych);
        self::assertSame(0, $vysledek->pocetZmenenych);

        $pocet = (int) dbOneCol(
            'SELECT COUNT(*) FROM shop_predmety WHERE kod_predmetu IN ($0)',
            [
                0 => ['POLOZKA_A', 'POLOZKA_B'],
            ],
        );
        self::assertSame(2, $pocet);

        // Verify tags were assigned
        $pocetTagu = (int) dbOneCol(<<<SQL
SELECT COUNT(*)
FROM product_product_tag ppt
JOIN product_tag pt ON ppt.tag_id = pt.id
JOIN shop_predmety sp ON ppt.product_id = sp.id_predmetu
WHERE sp.kod_predmetu IN ($0) AND pt.code = 'predmet'
SQL,
            [
                0 => ['POLOZKA_A', 'POLOZKA_B'],
            ],
        );
        self::assertSame(2, $pocetTagu);
    }

    /**
     * @test
     */
    public function importAktualizujeExistujiciPolozky(): void
    {
        $uniqueId = uniqid();

        // Create existing product via raw SQL
        dbQuery("INSERT INTO shop_predmety SET
            nazev = 'Původní název',
            kod_predmetu = 'UPDATE_{$uniqueId}',
            cena_aktualni = 100.00,
            stav = " . StavPredmetu::VEREJNY . ",
            popis = ''");
        $idPredmetu = dbInsertId();
        dbQuery("INSERT INTO product_product_tag (product_id, tag_id)
            SELECT {$idPredmetu}, id FROM product_tag WHERE code = 'predmet'");

        $soubor = $this->createXlsxSoubor([
            $this->defaultniRadek([
                'kod_predmetu'  => 'UPDATE_' . $uniqueId,
                'nazev'         => 'Nový název',
                'cena_aktualni' => '250.00',
            ]),
        ]);

        $importer = new EshopImporter($soubor);
        $vysledek = $importer->importuj();

        self::assertSame(0, $vysledek->pocetNovych);

        $cena = dbOneCol(
            'SELECT cena_aktualni FROM shop_predmety WHERE kod_predmetu = $0',
            [
                0 => 'UPDATE_' . $uniqueId,
            ],
        );
        self::assertSame('250.00', $cena);
    }

    /**
     * @test
     */
    public function importArchivujePolozkyCoNejsouVSouboru(): void
    {
        $uniqueId = uniqid();

        // Create two existing products
        dbQuery("INSERT INTO shop_predmety SET
            nazev = 'Zachovat {$uniqueId}',
            kod_predmetu = 'ZACHOVAT_{$uniqueId}',
            cena_aktualni = 100.00,
            stav = " . StavPredmetu::VEREJNY . ",
            popis = ''");
        $id1 = dbInsertId();
        dbQuery("INSERT INTO product_product_tag (product_id, tag_id)
            SELECT {$id1}, id FROM product_tag WHERE code = 'predmet'");

        dbQuery("INSERT INTO shop_predmety SET
            nazev = 'Archivovat {$uniqueId}',
            kod_predmetu = 'ARCHIVOVAT_{$uniqueId}',
            cena_aktualni = 100.00,
            stav = " . StavPredmetu::VEREJNY . ",
            popis = ''");
        $id2 = dbInsertId();
        dbQuery("INSERT INTO product_product_tag (product_id, tag_id)
            SELECT {$id2}, id FROM product_tag WHERE code = 'predmet'");

        // Import file only has the first product
        $soubor = $this->createXlsxSoubor([
            $this->defaultniRadek([
                'kod_predmetu' => 'ZACHOVAT_' . $uniqueId,
            ]),
        ]);

        $importer = new EshopImporter($soubor);
        $importer->importuj();

        // The missing product should be archived
        $archivedAt = dbOneCol(
            'SELECT archived_at FROM shop_predmety WHERE kod_predmetu = $0',
            [
                0 => 'ARCHIVOVAT_' . $uniqueId,
            ],
        );
        self::assertNotNull($archivedAt, 'Chybějící položka by měla být archivována');

        // The present product should NOT be archived
        $archivedAtZachovat = dbOneCol(
            'SELECT archived_at FROM shop_predmety WHERE kod_predmetu = $0',
            [
                0 => 'ZACHOVAT_' . $uniqueId,
            ],
        );
        self::assertNull($archivedAtZachovat, 'Přítomná položka nesmí být archivována');
    }

    /**
     * @test
     */
    public function importVygenerujeKodZNazvuKdyzChybi(): void
    {
        $soubor = $this->createXlsxSoubor([
            $this->defaultniRadek([
                'kod_predmetu' => '',
                'nazev'        => 'Moje Kostka',
            ]),
        ]);

        $importer = new EshopImporter($soubor);
        $vysledek = $importer->importuj();

        self::assertSame(1, $vysledek->pocetNovych);

        $pocet = (int) dbOneCol(
            'SELECT COUNT(*) FROM shop_predmety WHERE kod_predmetu = $0',
            [
                0 => 'moje_kostka',
            ],
        );
        self::assertSame(1, $pocet);
    }

    /**
     * @test
     */
    public function importChybaKdyzChybiPovinneSloupce(): void
    {
        $soubor = tempnam(sys_get_temp_dir(), 'eshop_import_test_') . '.xlsx';
        $writer = new XLSXWriter();
        $writer->openToFile($soubor);
        $writer->addRow(Row::fromValues(['nazev', 'kod_predmetu'])); // chybí ostatní sloupce
        $writer->addRow(Row::fromValues(['Předmět', 'KOD']));
        $writer->close();

        $this->expectException(\Chyba::class);
        $this->expectExceptionMessageMatches('/chybí sloupce/');

        $importer = new EshopImporter($soubor);
        $importer->importuj();
    }

    /**
     * @test
     */
    public function importChybaKdyzChybiTag(): void
    {
        $soubor = $this->createXlsxSoubor([
            $this->defaultniRadek([
                'tag' => '',
            ]),
        ]);

        $this->expectException(\Chyba::class);
        $this->expectExceptionMessageMatches('/řádku 2/');

        $importer = new EshopImporter($soubor);
        $importer->importuj();
    }

    /**
     * @test
     */
    public function importZpracujeNullHodnoty(): void
    {
        $soubor = $this->createXlsxSoubor([
            $this->defaultniRadek([
                'kod_predmetu'  => 'NULL_TEST',
                'kusu_vyrobeno' => 'NULL',
            ]),
        ]);

        $importer = new EshopImporter($soubor);
        $vysledek = $importer->importuj();

        self::assertSame(1, $vysledek->pocetNovych);

        $kusuVyrobeno = dbOneCol(
            'SELECT kusu_vyrobeno FROM shop_predmety WHERE kod_predmetu = $0',
            [
                0 => 'NULL_TEST',
            ],
        );
        self::assertNull($kusuVyrobeno);
    }

    /**
     * @test
     */
    public function importJeIdempotentni(): void
    {
        $soubor = $this->createXlsxSoubor([
            $this->defaultniRadek([
                'nazev'        => 'Idempotentní A',
                'kod_predmetu' => 'IDEMPOT_A',
            ]),
            $this->defaultniRadek([
                'nazev'        => 'Idempotentní B',
                'kod_predmetu' => 'IDEMPOT_B',
            ]),
        ]);

        $importer = new EshopImporter($soubor);
        $vysledek1 = $importer->importuj();

        self::assertSame(2, $vysledek1->pocetNovych);

        $importer2 = new EshopImporter($soubor);
        $vysledek2 = $importer2->importuj();

        self::assertSame(0, $vysledek2->pocetNovych);

        $pocet = (int) dbOneCol(
            'SELECT COUNT(*) FROM shop_predmety WHERE kod_predmetu IN ($0)',
            [
                0 => ['IDEMPOT_A', 'IDEMPOT_B'],
            ],
        );
        self::assertSame(2, $pocet);
    }
}
