<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

use App\Service\CapacityManager;
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

    /**
     * @param string[]|null $hlavicka
     */
    private function createXlsxSoubor(array $radky, ?array $hlavicka = null): string
    {
        $hlavicka ??= self::$hlavicka;
        $soubor = tempnam(sys_get_temp_dir(), 'eshop_import_test_') . '.xlsx';
        $writer = new XLSXWriter();
        $writer->openToFile($soubor);
        $writer->addRow(Row::fromValues($hlavicka));
        foreach ($radky as $radek) {
            $writer->addRow(Row::fromValues(array_map(
                static fn (string $sloupec) => $radek[$sloupec] ?? '',
                $hlavicka,
            )));
        }
        $writer->close();

        return $soubor;
    }

    /**
     * @param array<string, array<string, mixed>> $radky kód předmětu => přepsané hodnoty
     */
    private function souborSLetosnimi(array $radky): string
    {
        return $this->createXlsxSoubor(
            array_map(
                fn (string $kod, array $hodnoty): array => $this->defaultniRadek([
                    'kod_predmetu' => $kod,
                    'nazev'        => $kod,
                    ...$hodnoty,
                ]),
                array_keys($radky),
                $radky,
            ),
            [...self::$hlavicka, 'je_letosni_hlavni'],
        );
    }

    private function kodVPravidle(string $kodPravidla): ?string
    {
        $kod = dbOneCol(
            "SELECT JSON_VALUE(parameters, '$.productCode') FROM discount_rule WHERE code = $0 AND year = $1",
            [
                0 => $kodPravidla,
                1 => ROCNIK,
            ],
        );

        return $kod === null || $kod === false ? null : (string) $kod;
    }

    /**
     * @test
     */
    public function oznacenaLetosniKostkaAPlackaSeZapisouDoPravidel(): void
    {
        $vysledek = (new EshopImporter($this->souborSLetosnimi([
            'kostka_test_stara' => [
                'je_letosni_hlavni' => 0,
            ],
            'kostka_test_nova' => [
                'je_letosni_hlavni' => 1,
            ],
            'placka_test_nova' => [
                'je_letosni_hlavni' => 1,
            ],
        ])))->importuj();

        self::assertSame('kostka_test_nova', $this->kodVPravidle('kostka_zdarma'));
        self::assertSame('placka_test_nova', $this->kodVPravidle('placka_zdarma'));
        self::assertSame([], $vysledek->varovani);
    }

    /**
     * @test
     */
    public function dveOznaceneKostkyPravidloNezmeni(): void
    {
        $predtim = $this->kodVPravidle('kostka_zdarma');

        $vysledek = (new EshopImporter($this->souborSLetosnimi([
            'kostka_test_a' => [
                'je_letosni_hlavni' => 1,
            ],
            'kostka_test_b' => [
                'je_letosni_hlavni' => 1,
            ],
            'placka_test_nova' => [
                'je_letosni_hlavni' => 1,
            ],
        ])))->importuj();

        self::assertSame($predtim, $this->kodVPravidle('kostka_zdarma'));
        self::assertStringContainsString('kostka_test_a, kostka_test_b', implode("\n", $vysledek->varovani));
    }

    /**
     * @test
     */
    public function neoznacenaPlackaVaruje(): void
    {
        $vysledek = (new EshopImporter($this->souborSLetosnimi([
            'kostka_test_nova' => [
                'je_letosni_hlavni' => 1,
            ],
            'placka_test_nova' => [
                'je_letosni_hlavni' => 0,
            ],
        ])))->importuj();

        self::assertSame('kostka_test_nova', $this->kodVPravidle('kostka_zdarma'));
        self::assertStringContainsString('Placka zdarma', implode("\n", $vysledek->varovani));
    }

    /**
     * @test
     */
    public function oznacenyVyrazenyPredmetVaruje(): void
    {
        $vysledek = (new EshopImporter($this->souborSLetosnimi([
            'kostka_test_vyrazena' => [
                'je_letosni_hlavni' => 1,
                'stav'              => 0,
            ],
            'placka_test_nova' => [
                'je_letosni_hlavni' => 1,
            ],
        ])))->importuj();

        self::assertSame('kostka_test_vyrazena', $this->kodVPravidle('kostka_zdarma'));
        self::assertStringContainsString('kostka_test_vyrazena', implode("\n", $vysledek->varovani));
    }

    /**
     * A sheet without the column leaves the rules alone, but still says when the item a
     * rule names is no longer on offer, since nobody would then get it free.
     *
     * @test
     */
    public function bezSloupceSePravidlaNemeniAleVaruje(): void
    {
        $predtim = $this->kodVPravidle('kostka_zdarma');

        $vysledek = (new EshopImporter($this->createXlsxSoubor([
            $this->defaultniRadek([
                'kod_predmetu' => 'POLOZKA_BEZ_SLOUPCE',
            ]),
        ])))->importuj();

        self::assertSame($predtim, $this->kodVPravidle('kostka_zdarma'));
        self::assertStringContainsString('Kostka zdarma', implode("\n", $vysledek->varovani));
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
    public function importDaNovePolozceVychoziVariantu(): void
    {
        $soubor = $this->createXlsxSoubor([
            $this->defaultniRadek([
                'kod_predmetu'  => 'POLOZKA_S_VARIANTOU',
                'nazev'         => 'Předmět s variantou',
                'kusu_vyrobeno' => 7,
            ]),
        ]);

        $importer = new EshopImporter($soubor);
        $importer->importuj();

        // Bez varianty je předmět pro košík neviditelný — nejde ho do něj vložit.
        $varianta = dbOneLine(<<<SQL
SELECT product_variant.name, product_variant.code, product_variant.price
FROM product_variant
JOIN shop_predmety ON shop_predmety.id_predmetu = product_variant.product_id
WHERE shop_predmety.kod_predmetu = $0
SQL,
            [
                0 => 'POLOZKA_S_VARIANTOU',
            ],
        );

        self::assertNotNull($varianta, 'Nová položka musí dostat výchozí variantu.');
        self::assertNull($varianta['name'], 'Výchozí varianta nemá vlastní jméno, nese jméno produktu.');
        self::assertSame('POLOZKA_S_VARIANTOU', $varianta['code']);
        self::assertNull($varianta['price'], 'Cena se dědí z předmětu, aby ji jeho změna dál ovlivňovala.');
        self::assertSame(7, $this->zasobaVarianty('POLOZKA_S_VARIANTOU'));
    }

    /**
     * The sheet still has one row per night and size, so each row's state is its variant's.
     *
     * @test
     */
    public function importNastaviStavVarianty(): void
    {
        $importer = new EshopImporter($this->createXlsxSoubor([
            $this->defaultniRadek([
                'kod_predmetu' => 'POLOZKA_STAV',
                'stav'         => StavPredmetu::VEREJNY,
            ]),
        ]));
        $importer->importuj();
        self::assertSame(StavPredmetu::VEREJNY, $this->stavVarianty('POLOZKA_STAV'));

        $importer = new EshopImporter($this->createXlsxSoubor([
            $this->defaultniRadek([
                'kod_predmetu' => 'POLOZKA_STAV',
                'stav'         => StavPredmetu::POZASTAVENY,
            ]),
        ]));
        $importer->importuj();

        self::assertSame(StavPredmetu::POZASTAVENY, $this->stavVarianty('POLOZKA_STAV'));
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
     * Doctrine hydrates stav with from(), so a value outside the enum would make every
     * later read of that product throw rather than just look odd.
     *
     * @test
     */
    public function importChybaKdyzJeStavMimoRozsah(): void
    {
        // '' and 'abc' would cast to 0 and import as RETIRED if the check used (int).
        foreach ([9, -1, '', 'abc', '1.9'] as $neplatnyStav) {
            $soubor = $this->createXlsxSoubor([
                $this->defaultniRadek([
                    'stav' => $neplatnyStav,
                ]),
            ]);

            try {
                (new EshopImporter($soubor))->importuj();
                self::fail(sprintf('Stav %s měl být odmítnut', var_export($neplatnyStav, true)));
            } catch (\Chyba $chyba) {
                self::assertMatchesRegularExpression('/řádku 2.*stav/s', $chyba->getMessage());
            }
        }
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
            'SELECT kusu_vyrobeno FROM shop_predmety_s_typem WHERE kod_predmetu = $0',
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

    /**
     * @test
     */
    public function reimportNevratiZasobuProdanychKusu(): void
    {
        $soubor = $this->createXlsxSoubor([
            $this->defaultniRadek([
                'kod_predmetu'  => 'PRODANY',
                'kusu_vyrobeno' => 5,
            ]),
        ]);
        (new EshopImporter($soubor))->importuj();
        $this->nakupVarianty('PRODANY', kusu: 2);

        (new EshopImporter($soubor))->importuj();

        self::assertSame(3, $this->zasobaVarianty('PRODANY'));
    }

    /**
     * Nights and sizes hang under a shared owner, but each takes its capacity from its own row
     * sharing its code. The import must move their stock, never rename them.
     *
     * @test
     */
    public function reimportPosuneZasobuVariantPodSpolecnymVlastnikem(): void
    {
        dbQuery(<<<SQL
INSERT INTO shop_predmety (id_predmetu, nazev, kod_predmetu, cena_aktualni, stav, nabizet_do, popis)
VALUES (94201, 'Dvoulůžák', 'TYP_2L', 0, 1, NOW(), ''),
       (94202, 'Dvoulůžák pátek', 'NOC_2L_PA', 300, 1, NOW(), ''),
       (94203, 'Dvoulůžák sobota', 'NOC_2L_SO', 300, 1, NOW(), '')
SQL);
        dbQuery(<<<SQL
INSERT INTO product_variant (product_id, name, code, price, capacity, position, state)
VALUES (94201, 'pátek', 'NOC_2L_PA', 300, 4, 0, 1),
       (94201, 'sobota', 'NOC_2L_SO', 300, 4, 1, 1)
SQL);
        $this->nakupVarianty('NOC_2L_PA', kusu: 1);

        $soubor = $this->createXlsxSoubor([
            $this->defaultniRadek([
                'kod_predmetu'  => 'TYP_2L',
                'nazev'         => 'Dvoulůžák',
                'kusu_vyrobeno' => 'NULL',
                'tag'           => 'ubytovani',
            ]),
            $this->defaultniRadek([
                'kod_predmetu'  => 'NOC_2L_PA',
                'nazev'         => 'Dvoulůžák pátek',
                'kusu_vyrobeno' => 8,
                'tag'           => 'ubytovani',
            ]),
            $this->defaultniRadek([
                'kod_predmetu'  => 'NOC_2L_SO',
                'nazev'         => 'Dvoulůžák sobota',
                'kusu_vyrobeno' => 8,
                'tag'           => 'ubytovani',
            ]),
        ]);
        (new EshopImporter($soubor))->importuj();

        self::assertSame(7, $this->zasobaVarianty('NOC_2L_PA'));
        self::assertSame(8, $this->zasobaVarianty('NOC_2L_SO'));
        self::assertSame(
            'pátek',
            dbOneCol('SELECT name FROM product_variant WHERE code = $0', [
                0 => 'NOC_2L_PA',
            ]),
        );
    }

    private function nakupVarianty(string $kod, int $kusu): void
    {
        for ($kus = 0; $kus < $kusu; ++$kus) {
            dbQuery(<<<SQL
INSERT INTO shop_nakupy (id_uzivatele, id_objednatele, id_predmetu, variant_id, rok, cena_nakupni, datum)
SELECT $0, $0, shop_predmety.id_predmetu, product_variant.id, $1, 100, NOW()
FROM product_variant
JOIN shop_predmety ON shop_predmety.kod_predmetu = product_variant.code
WHERE product_variant.code = $2
SQL,
                [
                    0 => \Uzivatel::SYSTEM,
                    1 => ROCNIK,
                    2 => $kod,
                ],
            );
        }
    }

    /**
     * A night left out of the sheet is archived on its own row, while its room type stays.
     *
     * @test
     */
    public function nocVynechanaZImportuSeVyradi(): void
    {
        dbQuery(<<<SQL
INSERT INTO shop_predmety (id_predmetu, nazev, kod_predmetu, cena_aktualni, stav, nabizet_do, popis)
VALUES (94301, 'Trojlůžák', 'TYP_3L', 0, 3, NOW(), ''),
       (94302, 'Trojlůžák pátek', 'NOC_3L_PA', 300, 1, NOW(), ''),
       (94303, 'Trojlůžák sobota', 'NOC_3L_SO', 300, 1, NOW(), '')
SQL);
        dbQuery(<<<SQL
INSERT INTO product_variant (product_id, name, code, price, capacity, position, state)
VALUES (94301, 'pátek', 'NOC_3L_PA', 300, 4, 0, 1),
       (94301, 'sobota', 'NOC_3L_SO', 300, 4, 1, 1)
SQL);

        (new EshopImporter($this->createXlsxSoubor([
            $this->defaultniRadek([
                'kod_predmetu' => 'TYP_3L',
                'nazev'        => 'Trojlůžák',
                'stav'         => StavPredmetu::POZASTAVENY,
                'tag'          => 'ubytovani',
            ]),
            $this->defaultniRadek([
                'kod_predmetu'  => 'NOC_3L_PA',
                'nazev'         => 'Trojlůžák pátek',
                'tag'           => 'ubytovani',
                'ubytovani_den' => 2,
            ]),
        ])))->importuj();

        self::assertSame(StavPredmetu::VEREJNY, $this->stavVarianty('NOC_3L_PA'));
        self::assertSame(\App\Enum\ProductStateEnum::RETIRED->value, $this->stavVarianty('NOC_3L_SO'));
    }

    private function stavVarianty(string $kod): ?int
    {
        $stav = dbOneCol('SELECT state FROM product_variant WHERE code = $0', [
            0 => $kod,
        ]);

        return $stav === null ? null : (int) $stav;
    }

    private function zasobaVarianty(string $kod): ?int
    {
        $idVarianty = (int) dbOneCol('SELECT id FROM product_variant WHERE code = $0', [
            0 => $kod,
        ]);

        return static::getContainer()->get(CapacityManager::class)->remainingByVariantId([$idVarianty])[$idVarianty] ?? null;
    }
}
