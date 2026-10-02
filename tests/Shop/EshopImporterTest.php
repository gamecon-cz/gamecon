<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

use App\Enum\ProductStateEnum;
use App\Service\CapacityManager;
use App\Service\VariantStateMirror;
use Gamecon\Report\KonfiguraceReportu;
use Gamecon\Shop\EshopExport;
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

    /**
     * @param string[]|null $hlavicka
     */
    private function createXlsxSoubor(array $radky, ?array $hlavicka = null): string
    {
        $hlavicka ??= EshopImporter::SLOUPCE;
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
                    'product_code' => $kod,
                    'product_name' => $kod,
                    ...$hodnoty,
                ]),
                array_keys($radky),
                $radky,
            ),
            [...EshopImporter::SLOUPCE, EshopImporter::SLOUPEC_LETOSNI_HLAVNI],
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
                'product_code' => 'POLOZKA_BEZ_SLOUPCE',
            ]),
        ])))->importuj();

        self::assertSame($predtim, $this->kodVPravidle('kostka_zdarma'));
        self::assertStringContainsString('Kostka zdarma', implode("\n", $vysledek->varovani));
    }

    private function defaultniRadek(array $prepisVrednosti = []): array
    {
        $radek = [
            'product_name'   => 'Testovací předmět',
            'product_code'   => 'TEST_KOD',
            'variant_code'   => '',
            'variant_name'   => '',
            'archivovano'    => '',
            'tag'            => 'predmet',
            'cena_aktualni'  => '199.00',
            'stav'           => StavPredmetu::VEREJNY,
            'nabizet_do'     => '2025-12-31 00:00:00',
            'popis'          => 'Popis předmětu',
            'vedlejsi'       => 0,
            'snidane_v_cene' => 0,
            'cena_varianty'  => '',
            'stav_varianty'  => '',
            'kusu_vyrobeno'  => 100,
            'ubytovani_den'  => '',
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
                'product_code' => 'POLOZKA_A',
                'product_name' => 'Předmět A',
            ]),
            $this->defaultniRadek([
                'product_code' => 'POLOZKA_B',
                'product_name' => 'Předmět B',
            ]),
        ]);

        $vysledek = (new EshopImporter($soubor))->importuj();

        self::assertSame(2, $vysledek->pocetNovych);
        self::assertSame(0, $vysledek->pocetZmenenych);
        self::assertSame(
            [
                'POLOZKA_A' => 'predmet',
                'POLOZKA_B' => 'predmet',
            ],
            $this->tagyProduktu(['POLOZKA_A', 'POLOZKA_B']),
        );
    }

    /**
     * @test
     */
    public function importDaNovePolozceVychoziVariantu(): void
    {
        (new EshopImporter($this->createXlsxSoubor([
            $this->defaultniRadek([
                'product_code'  => 'POLOZKA_S_VARIANTOU',
                'product_name'  => 'Předmět s variantou',
                'kusu_vyrobeno' => 7,
            ]),
        ])))->importuj();

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
     * @test
     */
    public function importNastaviStavVarianty(): void
    {
        $radek = $this->defaultniRadek([
            'product_code' => 'POLOZKA_STAV',
            'stav'         => StavPredmetu::VEREJNY,
        ]);
        (new EshopImporter($this->createXlsxSoubor([$radek])))->importuj();
        self::assertSame(StavPredmetu::VEREJNY, $this->stavVarianty('POLOZKA_STAV'), 'Bez vlastního stavu varianta přebírá stav produktu');

        (new EshopImporter($this->createXlsxSoubor([[
            ...$radek,
            'stav' => StavPredmetu::POZASTAVENY,
        ]])))->importuj();

        self::assertSame(StavPredmetu::POZASTAVENY, $this->stavVarianty('POLOZKA_STAV'));
    }

    /**
     * An admin edit copies the product row's state onto the variant sharing its code, so a
     * different state for that variant would not last.
     *
     * @test
     */
    public function variantaSKodemProduktuNemaVlastniStav(): void
    {
        $this->expectException(\Chyba::class);
        $this->expectExceptionMessageMatches('/POLOZKA_STAV.*stav produktu/');

        (new EshopImporter($this->createXlsxSoubor([
            $this->defaultniRadek([
                'product_code'  => 'POLOZKA_STAV',
                'stav'          => StavPredmetu::VEREJNY,
                'stav_varianty' => StavPredmetu::POZASTAVENY,
            ]),
        ])))->importuj();
    }

    /**
     * @test
     */
    public function importAktualizujeExistujiciPolozky(): void
    {
        dbQuery("INSERT INTO shop_predmety SET
            id_predmetu = 94101,
            nazev = 'Původní název',
            kod_predmetu = 'UPDATE_KOD',
            cena_aktualni = 100.00,
            stav = " . StavPredmetu::VEREJNY . ",
            popis = ''");
        dbQuery("INSERT INTO product_variant (product_id, name, code, position, state) VALUES (94101, NULL, 'UPDATE_KOD', 0, 1)");

        $vysledek = (new EshopImporter($this->createXlsxSoubor([
            $this->defaultniRadek([
                'product_code'  => 'UPDATE_KOD',
                'product_name'  => 'Nový název',
                'cena_aktualni' => '250.00',
            ]),
        ])))->importuj();

        self::assertSame(0, $vysledek->pocetNovych);
        self::assertSame(1, $vysledek->pocetZmenenych);
        self::assertSame(
            [
                'nazev'         => 'Nový název',
                'cena_aktualni' => '250.00',
            ],
            dbOneLine('SELECT nazev, cena_aktualni FROM shop_predmety WHERE kod_predmetu = $0', [
                0 => 'UPDATE_KOD',
            ]),
        );
    }

    /**
     * @test
     */
    public function importArchivujePolozkyCoNejsouVSouboru(): void
    {
        dbQuery("INSERT INTO shop_predmety (id_predmetu, nazev, kod_predmetu, cena_aktualni, stav, popis)
            VALUES (94111, 'Zachovat', 'ZACHOVAT', 100, 1, ''), (94112, 'Archivovat', 'ARCHIVOVAT', 100, 1, '')");
        dbQuery("INSERT INTO product_variant (product_id, name, code, position, state)
            VALUES (94111, NULL, 'ZACHOVAT', 0, 1), (94112, NULL, 'ARCHIVOVAT', 0, 1)");

        $vysledek = (new EshopImporter($this->createXlsxSoubor([
            $this->defaultniRadek([
                'product_code' => 'ZACHOVAT',
            ]),
        ])))->importuj();

        self::assertNotNull($this->archivovano('ARCHIVOVAT'), 'Chybějící položka by měla být archivována');
        self::assertNull($this->archivovano('ZACHOVAT'), 'Přítomná položka nesmí být archivována');
        self::assertGreaterThanOrEqual(1, $vysledek->pocetVyrazenych);
    }

    /**
     * A past year's product carries its archive date in the sheet, so importing the whole
     * catalog back must not revive it as this year's.
     *
     * @test
     */
    public function archivovanyProduktZeSouboruZustaneArchivovany(): void
    {
        (new EshopImporter($this->createXlsxSoubor([
            $this->defaultniRadek([
                'product_code' => 'LONSKY',
                'archivovano'  => '2024-12-31 23:59:59',
            ]),
        ])))->importuj();

        self::assertSame('2024-12-31 23:59:59', $this->archivovano('LONSKY'));
    }

    /**
     * @test
     */
    public function importVygenerujeKodZNazvuKdyzChybi(): void
    {
        $vysledek = (new EshopImporter($this->createXlsxSoubor([
            $this->defaultniRadek([
                'product_code' => '',
                'product_name' => 'Moje Kostka',
            ]),
        ])))->importuj();

        self::assertSame(1, $vysledek->pocetNovych);
        self::assertSame('moje_kostka', dbOneCol(
            'SELECT product_variant.code FROM product_variant JOIN shop_predmety ON shop_predmety.id_predmetu = product_variant.product_id WHERE shop_predmety.kod_predmetu = $0',
            [
                0 => 'moje_kostka',
            ],
        ));
    }

    /**
     * @test
     */
    public function importChybaKdyzChybiPovinneSloupce(): void
    {
        $soubor = tempnam(sys_get_temp_dir(), 'eshop_import_test_') . '.xlsx';
        $writer = new XLSXWriter();
        $writer->openToFile($soubor);
        $writer->addRow(Row::fromValues(['nazev', 'kod_predmetu'])); // starý formát po řádcích katalogu
        $writer->addRow(Row::fromValues(['Předmět', 'KOD']));
        $writer->close();

        $this->expectException(\Chyba::class);
        $this->expectExceptionMessageMatches('/chybí sloupce .*variant_code/');

        (new EshopImporter($soubor))->importuj();
    }

    /**
     * @test
     */
    public function importChybaKdyzChybiTag(): void
    {
        $this->expectException(\Chyba::class);
        $this->expectExceptionMessageMatches('/řádku 2.*tag/');

        (new EshopImporter($this->createXlsxSoubor([
            $this->defaultniRadek([
                'tag' => '',
            ]),
        ])))->importuj();
    }

    /**
     * A tag that is not a category would be dropped silently, leaving the product with none.
     *
     * @test
     */
    public function importChybaKdyzTagNeniKategorie(): void
    {
        $this->expectException(\Chyba::class);
        $this->expectExceptionMessageMatches('/řádku 2.*mikina/');

        (new EshopImporter($this->createXlsxSoubor([
            $this->defaultniRadek([
                'tag' => 'mikina',
            ]),
        ])))->importuj();
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
        foreach ([
            'stav'          => [9, -1, '', 'abc', '1.9'],
            'stav_varianty' => [9, -1, 'abc', '1.9'],
        ] as $sloupec => $neplatneStavy) {
            foreach ($neplatneStavy as $neplatnyStav) {
                $soubor = $this->createXlsxSoubor([
                    $this->defaultniRadek([
                        $sloupec => $neplatnyStav,
                    ]),
                ]);

                try {
                    (new EshopImporter($soubor))->importuj();
                    self::fail(sprintf('%s %s měl být odmítnut', $sloupec, var_export($neplatnyStav, true)));
                } catch (\Chyba $chyba) {
                    self::assertMatchesRegularExpression('/řádku 2.*stav/s', $chyba->getMessage());
                }
            }
        }
    }

    /**
     * @test
     */
    public function importZpracujeNullHodnoty(): void
    {
        $vysledek = (new EshopImporter($this->createXlsxSoubor([
            $this->defaultniRadek([
                'product_code'  => 'NULL_TEST',
                'kusu_vyrobeno' => 'NULL',
            ]),
        ])))->importuj();

        self::assertSame(1, $vysledek->pocetNovych);
        self::assertNull(dbOneCol('SELECT capacity FROM product_variant WHERE code = $0', [
            0 => 'NULL_TEST',
        ]));
    }

    /**
     * @test
     */
    public function importJeIdempotentni(): void
    {
        $soubor = $this->createXlsxSoubor([
            $this->defaultniRadek([
                'product_name' => 'Idempotentní A',
                'product_code' => 'IDEMPOT_A',
            ]),
            $this->defaultniRadek([
                'product_name' => 'Idempotentní B',
                'product_code' => 'IDEMPOT_B',
            ]),
        ]);

        self::assertSame(2, (new EshopImporter($soubor))->importuj()->pocetNovych);
        $druhy = (new EshopImporter($soubor))->importuj();

        self::assertSame(0, $druhy->pocetNovych);
        self::assertSame(0, $druhy->pocetZmenenych, 'Beze změny v souboru se nic neupraví');
        self::assertSame(2, (int) dbOneCol('SELECT COUNT(*) FROM shop_predmety WHERE kod_predmetu IN ($0)', [
            0 => ['IDEMPOT_A', 'IDEMPOT_B'],
        ]));
    }

    /**
     * @test
     */
    public function reimportNevratiZasobuProdanychKusu(): void
    {
        $soubor = $this->createXlsxSoubor([
            $this->defaultniRadek([
                'product_code'  => 'PRODANY',
                'kusu_vyrobeno' => 5,
            ]),
        ]);
        (new EshopImporter($soubor))->importuj();
        $this->nakupVarianty('PRODANY', kusu: 2);

        (new EshopImporter($soubor))->importuj();

        self::assertSame(3, $this->zasobaVarianty('PRODANY'));
    }

    /**
     * Inserts a room type whose nights still have their own catalog rows, as the production
     * data does until those rows are deleted.
     */
    private function vlozTypPokojeSNocmi(): void
    {
        dbQuery(<<<SQL
INSERT INTO shop_predmety (id_predmetu, nazev, kod_predmetu, cena_aktualni, stav, nabizet_do, popis)
VALUES (94201, 'Dvoulůžák', 'TYP_2L', 0, 2, '2026-07-20 00:00:00', ''),
       (94202, 'Dvoulůžák pátek', 'NOC_2L_PA', 300, 1, '2026-07-20 00:00:00', ''),
       (94203, 'Dvoulůžák sobota', 'NOC_2L_SO', 300, 2, '2026-07-20 00:00:00', '')
SQL);
        dbQuery("INSERT INTO product_product_tag (product_id, tag_id) SELECT 94201, id FROM product_tag WHERE code = 'ubytovani'");
        dbQuery(<<<SQL
INSERT INTO product_variant (product_id, name, code, price, capacity, accommodation_day, position, state)
VALUES (94201, 'pátek', 'NOC_2L_PA', 300, 4, 2, 0, 1),
       (94201, 'sobota', 'NOC_2L_SO', 300, 4, 3, 1, 2)
SQL);
    }

    private function radekNoci(string $kod, string $noc, int $den, array $prepis = []): array
    {
        return $this->defaultniRadek([
            'product_code'  => 'TYP_2L',
            'product_name'  => 'Dvoulůžák',
            'variant_code'  => $kod,
            'variant_name'  => $noc,
            'tag'           => 'ubytovani',
            'cena_aktualni' => 0,
            'stav'          => 2,
            'nabizet_do'    => '2026-07-20 00:00:00',
            'popis'         => '',
            'cena_varianty' => 300,
            'stav_varianty' => 1,
            'kusu_vyrobeno' => 8,
            'ubytovani_den' => $den,
            ...$prepis,
        ]);
    }

    /**
     * Each night takes its capacity and state from its own sheet row; the import must move its
     * stock and keep its name.
     *
     * @test
     */
    public function reimportPosuneZasobuANastaviStavKazdeNoci(): void
    {
        $this->vlozTypPokojeSNocmi();
        $this->nakupVarianty('NOC_2L_PA', kusu: 1);

        (new EshopImporter($this->createXlsxSoubor([
            $this->radekNoci('NOC_2L_PA', 'pátek', 2),
            $this->radekNoci('NOC_2L_SO', 'sobota', 3, [
                'stav_varianty' => 1,
            ]),
        ])))->importuj();

        self::assertSame(7, $this->zasobaVarianty('NOC_2L_PA'));
        self::assertSame(8, $this->zasobaVarianty('NOC_2L_SO'));
        self::assertSame(1, $this->stavVarianty('NOC_2L_SO'));
        self::assertSame('pátek', dbOneCol('SELECT name FROM product_variant WHERE code = $0', [
            0 => 'NOC_2L_PA',
        ]));
    }

    /**
     * A night left out of the sheet is taken off the offer, while its room type stays; a later
     * edit of the room type in the admin must not bring it back.
     *
     * @test
     */
    public function nocVynechanaZImportuSeVyradi(): void
    {
        $this->vlozTypPokojeSNocmi();

        $vysledek = (new EshopImporter($this->createXlsxSoubor([
            $this->radekNoci('NOC_2L_PA', 'pátek', 2),
        ])))->importuj();

        self::assertSame(StavPredmetu::VEREJNY, $this->stavVarianty('NOC_2L_PA'));
        self::assertSame(ProductStateEnum::RETIRED->value, $this->stavVarianty('NOC_2L_SO'));
        self::assertNull($this->archivovano('TYP_2L'));
        self::assertSame(1, $vysledek->pocetZmenenych);

        static::getContainer()->get(VariantStateMirror::class)->mirror([94201]);

        self::assertSame(StavPredmetu::VEREJNY, $this->stavVarianty('NOC_2L_PA'));
        self::assertSame(ProductStateEnum::RETIRED->value, $this->stavVarianty('NOC_2L_SO'));
    }

    /**
     * A new size or night is a variant of its product, not a catalog row of its own.
     *
     * @test
     */
    public function novaNocPribudeJakoVariantaTypuPokoje(): void
    {
        $this->vlozTypPokojeSNocmi();

        $vysledek = (new EshopImporter($this->createXlsxSoubor([
            $this->radekNoci('NOC_2L_PA', 'pátek', 2),
            $this->radekNoci('NOC_2L_SO', 'sobota', 3, [
                'stav_varianty' => 2,
            ]),
            $this->radekNoci('NOC_2L_NE', 'neděle', 4),
        ])))->importuj();

        self::assertSame(0, $vysledek->pocetNovych);
        self::assertSame(
            [
                'product_id'        => '94201',
                'name'              => 'neděle',
                'accommodation_day' => '4',
                'capacity'          => '8',
                'position'          => '2',
            ],
            dbOneLine('SELECT product_id, name, accommodation_day, capacity, position FROM product_variant WHERE code = $0', [
                0 => 'NOC_2L_NE',
            ]),
        );
        self::assertSame(0, (int) dbOneCol('SELECT COUNT(*) FROM shop_predmety WHERE kod_predmetu = $0', [
            0 => 'NOC_2L_NE',
        ]));
    }

    /**
     * The product's own columns repeat on each of its rows; taking one of two different
     * values would silently drop the other.
     *
     * @test
     */
    public function lisiciSeProduktoveSloupceSeOdmitnou(): void
    {
        $this->expectException(\Chyba::class);
        $this->expectExceptionMessageMatches('/TYP_2L.*cena_aktualni/');

        (new EshopImporter($this->createXlsxSoubor([
            $this->radekNoci('NOC_2L_PA', 'pátek', 2),
            $this->radekNoci('NOC_2L_SO', 'sobota', 3, [
                'cena_aktualni' => 10,
            ]),
        ])))->importuj();
    }

    /**
     * @test
     */
    public function variantyProduktuMusiMitRuznaJmena(): void
    {
        foreach (['', 'pátek'] as $jmenoDruhe) {
            try {
                (new EshopImporter($this->createXlsxSoubor([
                    $this->radekNoci('NOC_2L_PA', 'pátek', 2),
                    $this->radekNoci('NOC_2L_SO', $jmenoDruhe, 3),
                ])))->importuj();
                self::fail(sprintf('Druhá varianta se jménem „%s" měla být odmítnuta', $jmenoDruhe));
            } catch (\Chyba $chyba) {
                self::assertStringContainsString('TYP_2L', $chyba->getMessage());
            }
        }
    }

    /**
     * Moving a variant to another product would leave its purchases under the old one.
     *
     * @test
     */
    public function variantaJinehoProduktuSeOdmitneANicSeNezapise(): void
    {
        $this->vlozTypPokojeSNocmi();

        try {
            (new EshopImporter($this->createXlsxSoubor([
                $this->defaultniRadek([
                    'product_code' => 'NOVY_PREDMET',
                ]),
                $this->defaultniRadek([
                    'product_code' => 'JINY_TYP',
                    'variant_code' => 'NOC_2L_PA',
                ]),
            ])))->importuj();
            self::fail('Varianta cizího produktu měla být odmítnuta');
        } catch (\Chyba $chyba) {
            self::assertStringContainsString('NOC_2L_PA', $chyba->getMessage());
        }

        self::assertSame(0, (int) dbOneCol("SELECT COUNT(*) FROM shop_predmety WHERE kod_predmetu IN ('NOVY_PREDMET', 'JINY_TYP')"));
        self::assertNull($this->archivovano('TYP_2L'), 'Odmítnutý import nesmí nic archivovat');
    }

    /**
     * A night's leftover catalog row is not a product, so its code cannot name one.
     *
     * @test
     */
    public function kodNociNejdePouzitJakoProdukt(): void
    {
        $this->vlozTypPokojeSNocmi();

        $this->expectException(\Chyba::class);
        $this->expectExceptionMessageMatches('/NOC_2L_SO/');

        (new EshopImporter($this->createXlsxSoubor([
            $this->defaultniRadek([
                'product_code' => 'NOC_2L_SO',
                'variant_code' => 'NOC_2L_SO_NOVY',
            ]),
        ])))->importuj();
    }

    /**
     * The export is the import's template: importing it back must change nothing, for this
     * year's offer as for past years and for products whose sizes still have catalog rows.
     *
     * @test
     */
    public function exportJdeBezeZmenyNaimportovatZpet(): void
    {
        $this->vlozTypPokojeSNocmi();
        dbQuery(<<<SQL
INSERT INTO shop_predmety (id_predmetu, nazev, kod_predmetu, cena_aktualni, stav, nabizet_do, popis, vedlejsi, archived_at)
VALUES (94401, 'Mikina', 'MIKINA_S', 600, 1, '2026-06-30 23:59:00', 'Hřejivá', 1, NULL),
       (94402, 'Mikina M', 'MIKINA_M', 600, 0, '2026-06-30 23:59:00', 'Hřejivá', 1, NULL),
       (94403, 'Loňská placka', 'PLACKA_LONI', 25.50, 1, NULL, '', 0, '2025-12-31 23:59:59')
SQL);
        dbQuery("INSERT INTO product_product_tag (product_id, tag_id) SELECT 94401, id FROM product_tag WHERE code IN ('predmet', 'mikina')");
        dbQuery("INSERT INTO product_product_tag (product_id, tag_id) SELECT 94403, id FROM product_tag WHERE code = 'predmet'");
        dbQuery(<<<SQL
INSERT INTO product_variant (product_id, name, code, price, capacity, accommodation_day, position, state)
VALUES (94401, 'S', 'MIKINA_S', NULL, 30, NULL, 3, 1),
       (94401, 'M', 'MIKINA_M', NULL, NULL, NULL, 5, 0),
       (94403, NULL, 'PLACKA_LONI', NULL, 100, NULL, 0, 1)
SQL);
        $predtim = $this->snimekKatalogu();

        $soubor = tempnam(sys_get_temp_dir(), 'eshop_export_test_') . '.xlsx';
        (new EshopExport(ROCNIK))->report()->tXlsx(null, (new KonfiguraceReportu())->setDestinationFile($soubor));
        $vysledek = (new EshopImporter($soubor))->importuj();

        self::assertSame($predtim, $this->snimekKatalogu());
        self::assertSame([0, 0, 0], [$vysledek->pocetNovych, $vysledek->pocetZmenenych, $vysledek->pocetVyrazenych]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snimekKatalogu(): array
    {
        return [
            'produkty' => dbFetchAll('SELECT * FROM shop_predmety ORDER BY id_predmetu'),
            'varianty' => dbFetchAll('SELECT * FROM product_variant ORDER BY id'),
            'tagy'     => dbFetchAll('SELECT product_id, tag_id FROM product_product_tag ORDER BY product_id, tag_id'),
            'pravidla' => dbFetchAll('SELECT code, year, parameters FROM discount_rule ORDER BY id'),
        ];
    }

    private function nakupVarianty(string $kod, int $kusu): void
    {
        for ($kus = 0; $kus < $kusu; ++$kus) {
            dbQuery(<<<SQL
INSERT INTO shop_nakupy (id_uzivatele, id_objednatele, id_predmetu, variant_id, rok, cena_nakupni, datum)
SELECT $0, $0, product_variant.product_id, product_variant.id, $1, 100, NOW()
FROM product_variant
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
     * @param string[] $kody
     *
     * @return array<string, string> kód produktu => kód jeho kategorie
     */
    private function tagyProduktu(array $kody): array
    {
        return array_column(dbFetchAll(<<<SQL
SELECT shop_predmety.kod_predmetu, product_tag.code
FROM product_product_tag
JOIN product_tag ON product_tag.id = product_product_tag.tag_id
JOIN shop_predmety ON shop_predmety.id_predmetu = product_product_tag.product_id
WHERE shop_predmety.kod_predmetu IN ($0)
ORDER BY shop_predmety.kod_predmetu
SQL,
            [
                0 => $kody,
            ],
        ), 'code', 'kod_predmetu');
    }

    private function archivovano(string $kodProduktu): ?string
    {
        $archivovano = dbOneCol('SELECT archived_at FROM shop_predmety WHERE kod_predmetu = $0', [
            0 => $kodProduktu,
        ]);

        return $archivovano === null || $archivovano === false ? null : (string) $archivovano;
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
