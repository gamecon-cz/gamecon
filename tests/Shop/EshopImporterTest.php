<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

use App\Structure\Entity\ShopItemEntityStructure;
use Gamecon\Shop\EshopImporter;
use Gamecon\Shop\StavPredmetu;
use Gamecon\Shop\TypPredmetu;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Tests\Factory\ShopItemFactory;
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
        'model_rok',
        'nazev',
        'kod_predmetu',
        'cena_aktualni',
        'stav',
        'nabizet_do',
        'kusu_vyrobeno',
        'typ',
        'podtyp',
        'je_letosni_hlavni',
        'ubytovani_den',
        'popis',
        'vedlejsi',
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
            'model_rok'         => 2025,
            'nazev'             => 'Testovací předmět',
            'kod_predmetu'      => 'TEST_KOD',
            'cena_aktualni'     => '199.00',
            'stav'              => StavPredmetu::VEREJNY,
            'nabizet_do'        => '2025-12-31',
            'kusu_vyrobeno'     => 100,
            'typ'               => TypPredmetu::PREDMET,
            'podtyp'            => '',
            'je_letosni_hlavni' => 0,
            'ubytovani_den'     => '',
            'popis'             => 'Popis předmětu',
            'vedlejsi'          => 0,
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
            'SELECT COUNT(*) FROM shop_predmety WHERE model_rok = $0 AND kod_predmetu IN ($1)',
            [
                0 => 2025,
                1 => ['POLOZKA_A', 'POLOZKA_B'],
            ],
        );
        self::assertSame(2, $pocet);
    }

    /**
     * @test
     */
    public function importAktualizujeExistujiciPolozky(): void
    {
        $uniqueId = uniqid();
        ShopItemFactory::createOne([
            ShopItemEntityStructure::nazev        => 'Původní název',
            ShopItemEntityStructure::kodPredmetu  => 'UPDATE_' . $uniqueId,
            ShopItemEntityStructure::modelRok     => 2025,
            ShopItemEntityStructure::cenaAktualni => '100.00',
            ShopItemEntityStructure::stav         => StavPredmetu::VEREJNY,
            ShopItemEntityStructure::typ          => TypPredmetu::PREDMET,
        ]);

        $soubor = $this->createXlsxSoubor([
            $this->defaultniRadek([
                'kod_predmetu'  => 'UPDATE_' . $uniqueId,
                'nazev'         => 'Nový název',
                'cena_aktualni' => '250.00',
                'model_rok'     => 2025,
            ]),
        ]);

        $importer = new EshopImporter($soubor);
        $vysledek = $importer->importuj();

        self::assertSame(0, $vysledek->pocetNovych);

        $cena = dbOneCol(
            'SELECT cena_aktualni FROM shop_predmety WHERE kod_predmetu = $0 AND model_rok = $1',
            [
                0 => 'UPDATE_' . $uniqueId,
                1 => 2025,
            ],
        );
        self::assertSame('250.00', $cena);
    }

    /**
     * @test
     */
    public function importVyradiPolozkyCoNejsouVSouboru(): void
    {
        $uniqueId = uniqid();
        ShopItemFactory::createOne([
            ShopItemEntityStructure::nazev       => 'Zachovat ' . $uniqueId,
            ShopItemEntityStructure::kodPredmetu => 'ZACHOVAT_' . $uniqueId,
            ShopItemEntityStructure::modelRok    => 2025,
            ShopItemEntityStructure::stav        => StavPredmetu::VEREJNY,
            ShopItemEntityStructure::typ         => TypPredmetu::PREDMET,
        ]);
        ShopItemFactory::createOne([
            ShopItemEntityStructure::nazev       => 'Vyradit ' . $uniqueId,
            ShopItemEntityStructure::kodPredmetu => 'VYRADIT_' . $uniqueId,
            ShopItemEntityStructure::modelRok    => 2025,
            ShopItemEntityStructure::stav        => StavPredmetu::VEREJNY,
            ShopItemEntityStructure::typ         => TypPredmetu::PREDMET,
        ]);

        $soubor = $this->createXlsxSoubor([
            $this->defaultniRadek([
                'kod_predmetu' => 'ZACHOVAT_' . $uniqueId,
                'model_rok'    => 2025,
            ]),
        ]);

        $importer = new EshopImporter($soubor);
        $importer->importuj();

        $stavVyrazene = (int) dbOneCol(
            'SELECT stav FROM shop_predmety WHERE kod_predmetu = $0 AND model_rok = $1',
            [
                0 => 'VYRADIT_' . $uniqueId,
                1 => 2025,
            ],
        );
        self::assertSame(StavPredmetu::MIMO, $stavVyrazene);
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
                'model_rok'    => 2025,
            ]),
        ]);

        $importer = new EshopImporter($soubor);
        $vysledek = $importer->importuj();

        self::assertSame(1, $vysledek->pocetNovych);

        $pocet = (int) dbOneCol(
            'SELECT COUNT(*) FROM shop_predmety WHERE kod_predmetu = $0 AND model_rok = $1',
            [
                0 => 'moje_kostka',
                1 => 2025,
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
        $writer->addRow(Row::fromValues(['model_rok', 'nazev'])); // chybí ostatní sloupce
        $writer->addRow(Row::fromValues([2025, 'Předmět']));
        $writer->close();

        $this->expectException(\Chyba::class);
        $this->expectExceptionMessageMatches('/chybí sloupce/');

        $importer = new EshopImporter($soubor);
        $importer->importuj();
    }

    /**
     * @test
     */
    public function importChybaKdyzChybiModelRok(): void
    {
        $soubor = $this->createXlsxSoubor([
            $this->defaultniRadek([
                'model_rok' => '',
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
                'model_rok'     => 2025,
            ]),
        ]);

        $importer = new EshopImporter($soubor);
        $vysledek = $importer->importuj();

        self::assertSame(1, $vysledek->pocetNovych);

        $kusuVyrobeno = dbOneCol(
            'SELECT kusu_vyrobeno FROM shop_predmety WHERE kod_predmetu = $0 AND model_rok = $1',
            [
                0 => 'NULL_TEST',
                1 => 2025,
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
                'model_rok'    => 2025,
            ]),
            $this->defaultniRadek([
                'nazev'        => 'Idempotentní B',
                'kod_predmetu' => 'IDEMPOT_B',
                'model_rok'    => 2025,
            ]),
        ]);

        $importer = new EshopImporter($soubor);
        $vysledek1 = $importer->importuj();

        self::assertSame(2, $vysledek1->pocetNovych);

        $importer2 = new EshopImporter($soubor);
        $vysledek2 = $importer2->importuj();

        self::assertSame(0, $vysledek2->pocetNovych);

        $pocet = (int) dbOneCol(
            'SELECT COUNT(*) FROM shop_predmety WHERE kod_predmetu IN ($0) AND model_rok = $1',
            [
                0 => ['IDEMPOT_A', 'IDEMPOT_B'],
                1 => 2025,
            ],
        );
        self::assertSame(2, $pocet);
    }
}
