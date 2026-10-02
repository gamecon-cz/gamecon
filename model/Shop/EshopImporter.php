<?php

declare(strict_types=1);

namespace Gamecon\Shop;

use App\Enum\ProductStateEnum;
use App\Enum\ProductTagCode;
use OpenSpout\Reader\XLSX\Reader as XLSXReader;

/**
 * Reads the sheet `EshopExport` writes: one row per variant, the product's own columns
 * repeated on each of its rows.
 */
class EshopImporter
{
    public const SLOUPCE = [
        'product_name',
        'product_code',
        'variant_code',
        'variant_name',
        'archivovano',
        'tag',
        'cena_aktualni',
        'stav',
        'nabizet_do',
        'popis',
        'vedlejsi',
        'snidane_v_cene',
        'cena_varianty',
        'stav_varianty',
        'kusu_vyrobeno',
        'ubytovani_den',
    ];

    // Optional: names this year's free dice and badge, see LetosniPredmetyZdarma.
    public const SLOUPEC_LETOSNI_HLAVNI = 'je_letosni_hlavni';

    private const SLOUPCE_PRODUKTU = [
        'product_name',
        'archivovano',
        'tag',
        'cena_aktualni',
        'stav',
        'nabizet_do',
        'popis',
        'vedlejsi',
        'snidane_v_cene',
        self::SLOUPEC_LETOSNI_HLAVNI,
    ];

    public function __construct(
        private readonly string $souborCesta,
        private readonly int $rocnik = ROCNIK,
    ) {
    }

    public function importuj(): EshopImportVysledek
    {
        [$radky, $maSloupecLetosni] = $this->nactiRadky();
        $produkty = $this->seskupPodleProduktu($radky);

        $pocetNovych = 0;
        $pocetZmenenych = 0;
        dbBegin();
        try {
            $this->odmitniCiziKody($produkty);
            foreach ($produkty as $produkt) {
                $idProduktu = $this->idProduktu($produkt['product_code']);
                if ($idProduktu === null) {
                    $this->vlozProdukt($produkt);
                    ++$pocetNovych;
                } elseif ($this->upravProdukt($idProduktu, $produkt)) {
                    ++$pocetZmenenych;
                }
            }
            $pocetVyrazenych = $this->archivujChybejici(array_column($produkty, 'product_code'));
            dbCommit();
        } catch (\Throwable $chyba) {
            dbRollback();
            throw $chyba;
        }

        $oznaceneLetosni = array_column(array_filter(
            $produkty,
            static fn (array $produkt): bool => $produkt[self::SLOUPEC_LETOSNI_HLAVNI] === 1,
        ), 'product_code');
        $varovani = (new LetosniPredmetyZdarma($this->rocnik))->nastavZImportu(
            $maSloupecLetosni ? $oznaceneLetosni : null,
        );

        return new EshopImportVysledek($pocetNovych, $pocetZmenenych, $pocetVyrazenych, $varovani);
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: bool} rows with their sheet line under `radek`
     */
    private function nactiRadky(): array
    {
        if (! is_readable($this->souborCesta)) {
            throw new \Chyba('Soubor se nepodařilo načíst');
        }

        $reader = new XLSXReader();
        $reader->open($this->souborCesta);
        $reader->getSheetIterator()->rewind();
        /** @var \OpenSpout\Reader\SheetInterface $sheet */
        $sheet = $reader->getSheetIterator()->current();
        $rowIterator = $sheet->getRowIterator();
        $rowIterator->rewind();

        $hlavicka = array_flip(array_map(static fn ($sloupec) => trim((string) $sloupec), $rowIterator->current()->toArray()));
        $chybejici = array_diff(self::SLOUPCE, array_keys($hlavicka));
        if ($chybejici !== []) {
            $reader->close();
            throw new \Chyba('Chybný formát souboru - chybí sloupce ' . implode(',', $chybejici));
        }
        $maSloupecLetosni = isset($hlavicka[self::SLOUPEC_LETOSNI_HLAVNI]);

        $chyby = [];
        $radky = [];
        $cisloRadku = 1;
        for ($rowIterator->next(); $rowIterator->valid(); $rowIterator->next()) {
            ++$cisloRadku;
            $bunky = $rowIterator->current()->toArray();
            $hodnoty = [];
            foreach ([...self::SLOUPCE, self::SLOUPEC_LETOSNI_HLAVNI] as $sloupec) {
                $hodnoty[$sloupec] = isset($hlavicka[$sloupec])
                    ? $this->hodnotaBunky($bunky[$hlavicka[$sloupec]] ?? null)
                    : null;
            }
            if (array_filter($hodnoty, static fn (?string $hodnota): bool => $hodnota !== null) === []) {
                continue;
            }
            try {
                $radky[] = $this->radek($hodnoty, $cisloRadku);
            } catch (\Chyba $chyba) {
                $chyby[] = $chyba->getMessage();
            }
        }
        $reader->close();

        if ($chyby !== []) {
            throw new \Chyba('Chybička se vloudila: ' . implode('; ', $chyby));
        }

        return [$radky, $maSloupecLetosni];
    }

    /**
     * Empty cells and the text NULL read as no value.
     */
    private function hodnotaBunky(mixed $bunka): ?string
    {
        if ($bunka instanceof \DateTimeInterface) {
            return $bunka->format('Y-m-d H:i:s');
        }
        $hodnota = trim((string) $bunka);

        return $hodnota === '' || strtoupper($hodnota) === 'NULL'
            ? null
            : $hodnota;
    }

    /**
     * @param array<string, string|null> $hodnoty
     *
     * @return array<string, mixed>
     */
    private function radek(array $hodnoty, int $cisloRadku): array
    {
        $chyba = static fn (string $zprava): \Chyba => new \Chyba(sprintf('Na řádku %d %s', $cisloRadku, $zprava));

        if ($hodnoty['product_name'] === null) {
            throw $chyba('chybí product_name');
        }
        $kategorie = array_map(static fn (ProductTagCode $tag): string => $tag->value, ProductTagCode::categories());
        if ($hodnoty['tag'] === null) {
            throw $chyba('chybí tag');
        }
        if (! in_array($hodnoty['tag'], $kategorie, true)) {
            throw $chyba(sprintf('je tag "%s", povolené jsou %s', $hodnoty['tag'], implode(', ', $kategorie)));
        }
        $stav = $this->stav($hodnoty['stav'], 'stav', $chyba);
        $cisloNeboNull = static function (?string $hodnota, string $sloupec) use ($chyba): ?string {
            if ($hodnota !== null && ! is_numeric($hodnota)) {
                throw $chyba(sprintf('není %s "%s" číslo', $sloupec, $hodnota));
            }

            return $hodnota;
        };
        $datumNeboNull = static function (?string $hodnota, string $sloupec) use ($chyba): ?string {
            if ($hodnota === null) {
                return null;
            }
            $datum = date_create_immutable($hodnota);
            if ($datum === false) {
                throw $chyba(sprintf('není %s "%s" datum', $sloupec, $hodnota));
            }

            return $datum->format('Y-m-d H:i:s');
        };
        $productCode = $hodnoty['product_code'] ?? kodZNazvu($hodnoty['product_name']);

        return [
            'radek'                      => $cisloRadku,
            'product_name'               => $hodnoty['product_name'],
            'product_code'               => $productCode,
            'variant_code'               => $hodnoty['variant_code'],
            'variant_name'               => $hodnoty['variant_name'],
            'archivovano'                => $datumNeboNull($hodnoty['archivovano'], 'archivovano'),
            'tag'                        => $hodnoty['tag'],
            'cena_aktualni'              => $cisloNeboNull($hodnoty['cena_aktualni'], 'cena_aktualni') ?? '0',
            'stav'                       => $stav,
            'nabizet_do'                 => $datumNeboNull($hodnoty['nabizet_do'], 'nabizet_do'),
            'popis'                      => $hodnoty['popis'] ?? '',
            'vedlejsi'                   => (int) (bool) $hodnoty['vedlejsi'],
            'snidane_v_cene'             => (int) (bool) $hodnoty['snidane_v_cene'],
            self::SLOUPEC_LETOSNI_HLAVNI => (int) (bool) $hodnoty[self::SLOUPEC_LETOSNI_HLAVNI],
            'cena_varianty'              => $cisloNeboNull($hodnoty['cena_varianty'], 'cena_varianty'),
            'stav_varianty'              => $hodnoty['stav_varianty'] === null
                ? $stav
                : $this->stav($hodnoty['stav_varianty'], 'stav_varianty', $chyba),
            'kusu_vyrobeno' => $cisloNeboNull($hodnoty['kusu_vyrobeno'], 'kusu_vyrobeno'),
            'ubytovani_den' => $cisloNeboNull($hodnoty['ubytovani_den'], 'ubytovani_den'),
        ];
    }

    /**
     * Checked as a string, not cast: (int) would turn "abc" into 0 and silently import the
     * product as RETIRED.
     */
    private function stav(?string $hodnota, string $sloupec, callable $chyba): int
    {
        if ($hodnota === null || ! ctype_digit($hodnota) || ProductStateEnum::tryFrom((int) $hodnota) === null) {
            throw $chyba(sprintf('je neplatný %s "%s", povolené jsou %s', $sloupec, $hodnota, implode(', ', array_column(ProductStateEnum::cases(), 'value'))));
        }

        return (int) $hodnota;
    }

    /**
     * @param list<array<string, mixed>> $radky
     *
     * @return array<string, array<string, mixed>> product code => product columns plus `varianty`
     */
    private function seskupPodleProduktu(array $radky): array
    {
        $produkty = [];
        foreach ($radky as $radek) {
            $produkty[$radek['product_code']][] = $radek;
        }

        $chyby = [];
        $kodyVariant = [];
        $vysledek = [];
        foreach ($produkty as $kodProduktu => $radkyProduktu) {
            $prvni = $radkyProduktu[0];
            foreach (array_slice($radkyProduktu, 1) as $radek) {
                foreach (self::SLOUPCE_PRODUKTU as $sloupec) {
                    if ($radek[$sloupec] !== $prvni[$sloupec]) {
                        $chyby[] = sprintf(
                            'produkt %s má na řádcích %d a %d různé %s',
                            $kodProduktu,
                            $prvni['radek'],
                            $radek['radek'],
                            $sloupec,
                        );
                    }
                }
            }

            $jedinaVarianta = count($radkyProduktu) === 1;
            $jmena = [];
            $varianty = [];
            foreach ($radkyProduktu as $radek) {
                if (! $jedinaVarianta) {
                    if ($radek['variant_name'] === null) {
                        $chyby[] = sprintf('produkt %s má víc variant, ta na řádku %d potřebuje variant_name', $kodProduktu, $radek['radek']);
                    } elseif (isset($jmena[$radek['variant_name']])) {
                        $chyby[] = sprintf('produkt %s má dvě varianty „%s"', $kodProduktu, $radek['variant_name']);
                    }
                    $jmena[(string) $radek['variant_name']] = true;
                }
                $kodVarianty = $radek['variant_code'] ?? ($jedinaVarianta
                    ? (string) $kodProduktu
                    : kodZNazvu($radek['product_name'] . ' ' . $radek['variant_name']));
                if (isset($kodyVariant[$kodVarianty])) {
                    $chyby[] = sprintf('kód varianty %s je na řádcích %d a %d', $kodVarianty, $kodyVariant[$kodVarianty], $radek['radek']);
                }
                $kodyVariant[$kodVarianty] = $radek['radek'];
                // VariantStateMirror copies the product row's state onto the variant sharing its code.
                if ($kodVarianty === (string) $kodProduktu && $radek['stav_varianty'] !== $radek['stav']) {
                    $chyby[] = sprintf('varianta %s na řádku %d má kód produktu, její stav je stav produktu', $kodVarianty, $radek['radek']);
                }
                $varianty[] = [
                    'code'              => $kodVarianty,
                    'name'              => $radek['variant_name'],
                    'price'             => $radek['cena_varianty'],
                    'state'             => $radek['stav_varianty'],
                    'capacity'          => $radek['kusu_vyrobeno'],
                    'accommodation_day' => $radek['ubytovani_den'],
                ];
            }

            $vysledek[(string) $kodProduktu] = [
                ...array_intersect_key($prvni, array_flip([...self::SLOUPCE_PRODUKTU, 'product_code'])),
                'product_code' => (string) $kodProduktu,
                'varianty'     => $varianty,
            ];
        }

        if ($chyby !== []) {
            throw new \Chyba('Chybička se vloudila: ' . implode('; ', $chyby));
        }

        return $vysledek;
    }

    /**
     * A variant moved to another product would leave its purchases under the old one, and a
     * night's or size's own catalog row is not a product.
     *
     * @param array<string, array<string, mixed>> $produkty
     */
    private function odmitniCiziKody(array $produkty): void
    {
        $chyby = [];
        foreach ($produkty as $produkt) {
            $kodProduktu = $produkt['product_code'];
            $idProduktu = $this->idProduktu($kodProduktu);
            $vlastnikKodu = $this->vlastnikVarianty($kodProduktu);
            $kodyVariant = array_column($produkt['varianty'], 'code');
            if ($vlastnikKodu !== null && $vlastnikKodu !== $idProduktu) {
                $chyby[] = sprintf('kód %s patří variantě jiného produktu, produkt jím pojmenovat nejde', $kodProduktu);
                continue;
            }
            // VariantStateMirror copies the product row's state onto this variant, so retiring it would not last.
            if ($vlastnikKodu !== null && ! in_array($kodProduktu, $kodyVariant, true)) {
                $chyby[] = sprintf('varianta %s má kód produktu a nejde ji vynechat, dej jí variant_name a nech ji v listu', $kodProduktu);
            }
            foreach ($kodyVariant as $kodVarianty) {
                if ($kodVarianty !== $kodProduktu && isset($produkty[$kodVarianty])) {
                    $chyby[] = sprintf('kód varianty %s je v listu i kódem jiného produktu', $kodVarianty);
                    continue;
                }
                $vlastnik = $this->vlastnikVarianty($kodVarianty);
                if ($vlastnik !== null && $vlastnik !== $idProduktu) {
                    $chyby[] = sprintf('varianta %s patří jinému produktu než %s', $kodVarianty, $kodProduktu);
                    continue;
                }
                $jinyRadek = $vlastnik === null
                    ? $this->idProduktu($kodVarianty)
                    : null;
                // The row would turn into the new variant's own row, driving its state.
                if ($jinyRadek !== null && $jinyRadek !== $idProduktu) {
                    $chyby[] = sprintf('kód varianty %s už má jiný řádek katalogu', $kodVarianty);
                }
            }
        }
        if ($chyby !== []) {
            throw new \Chyba('Chybička se vloudila: ' . implode('; ', $chyby));
        }
    }

    private function vlastnikVarianty(string $kodVarianty): ?int
    {
        $idProduktu = dbOneCol('SELECT product_id FROM product_variant WHERE code = $0', [
            0 => $kodVarianty,
        ]);

        return $idProduktu === null || $idProduktu === false ? null : (int) $idProduktu;
    }

    private function idProduktu(string $kodProduktu): ?int
    {
        $idProduktu = dbOneCol('SELECT id_predmetu FROM shop_predmety WHERE kod_predmetu = $0', [
            0 => $kodProduktu,
        ]);

        return $idProduktu === null || $idProduktu === false ? null : (int) $idProduktu;
    }

    /**
     * @param array<string, mixed> $produkt
     */
    private function vlozProdukt(array $produkt): void
    {
        dbQuery(
            'INSERT INTO shop_predmety (nazev, kod_predmetu, cena_aktualni, stav, nabizet_do, popis, vedlejsi, breakfast_included, archived_at)
             VALUES ($0, $1, $2, $3, $4, $5, $6, $7, $8)',
            [
                0 => $produkt['product_name'],
                1 => $produkt['product_code'],
                2 => $produkt['cena_aktualni'],
                3 => $produkt['stav'],
                4 => $produkt['nabizet_do'],
                5 => $produkt['popis'],
                6 => $produkt['vedlejsi'],
                7 => $produkt['snidane_v_cene'],
                8 => $produkt['archivovano'],
            ],
        );
        $this->zapisVarianty((int) dbInsertId(), $produkt);
    }

    /**
     * @param array<string, mixed> $produkt
     *
     * @return bool whether anything of the product or its variants changed
     */
    private function upravProdukt(int $idProduktu, array $produkt): bool
    {
        $zmeneno = dbAffectedOrNumRows(dbQuery(
            'UPDATE shop_predmety
             SET nazev = $1, cena_aktualni = $2, stav = $3, nabizet_do = $4, popis = $5, vedlejsi = $6,
                 breakfast_included = $7, archived_at = $8
             WHERE id_predmetu = $0',
            [
                0 => $idProduktu,
                1 => $produkt['product_name'],
                2 => $produkt['cena_aktualni'],
                3 => $produkt['stav'],
                4 => $produkt['nabizet_do'],
                5 => $produkt['popis'],
                6 => $produkt['vedlejsi'],
                7 => $produkt['snidane_v_cene'],
                8 => $produkt['archivovano'],
            ],
        )) > 0;

        return $this->zapisVarianty($idProduktu, $produkt) || $zmeneno;
    }

    /**
     * @param array<string, mixed> $produkt
     *
     * @return bool whether anything changed
     */
    private function zapisVarianty(int $idProduktu, array $produkt): bool
    {
        $zmeneno = $this->nastavKategorii($idProduktu, $produkt['tag']);
        $pozice = (int) dbOneCol('SELECT COALESCE(MAX(position) + 1, 0) FROM product_variant WHERE product_id = $0', [
            0 => $idProduktu,
        ]);
        foreach ($produkt['varianty'] as $varianta) {
            $hodnoty = [
                0 => $idProduktu,
                1 => $varianta['name'],
                2 => $varianta['code'],
                3 => $varianta['price'],
                4 => $varianta['capacity'],
                5 => $varianta['accommodation_day'],
                6 => $varianta['state'],
            ];
            if (dbOneCol('SELECT 1 FROM product_variant WHERE code = $0', [
                0 => $varianta['code'],
            ])) {
                $zmeneno = dbAffectedOrNumRows(dbQuery(
                    'UPDATE product_variant SET name = $1, price = $3, capacity = $4, accommodation_day = $5, state = $6
                     WHERE code = $2 AND product_id = $0',
                    $hodnoty,
                )) > 0 || $zmeneno;
            } else {
                dbQuery(
                    'INSERT INTO product_variant (product_id, name, code, price, capacity, accommodation_day, state, position)
                     VALUES ($0, $1, $2, $3, $4, $5, $6, $7)',
                    [
                        ...$hodnoty,
                        7 => $pozice++,
                    ],
                );
                $zmeneno = true;
            }
        }

        $zmeneno = dbAffectedOrNumRows(dbQuery(
            'UPDATE product_variant SET state = $1 WHERE product_id = $0 AND code NOT IN ($2) AND state <> $1',
            [
                0 => $idProduktu,
                1 => ProductStateEnum::RETIRED->value,
                2 => array_column($produkt['varianty'], 'code'),
            ],
        )) > 0 || $zmeneno;

        // A product sold as one variant still carries its day on its own row, where legacy reads it.
        dbQuery(
            'UPDATE shop_predmety
             SET ubytovani_den = (
                 SELECT IF(COUNT(*) = 1, MIN(varianta.accommodation_day), NULL)
                 FROM product_variant AS varianta
                 WHERE varianta.product_id = $0
                   AND (varianta.state <> $1 OR NOT EXISTS (
                       SELECT 1 FROM product_variant AS aktivni WHERE aktivni.product_id = $0 AND aktivni.state <> $1
                   ))
             )
             WHERE id_predmetu = $0',
            [
                0 => $idProduktu,
                1 => ProductStateEnum::RETIRED->value,
            ],
        );
        $this->srovnejZbyleRadky($idProduktu);

        return $zmeneno;
    }

    private function nastavKategorii(int $idProduktu, string $tag): bool
    {
        $kategorie = array_map(static fn (ProductTagCode $kategorie): string => $kategorie->value, ProductTagCode::categories());
        $soucasna = dbOneArray(
            'SELECT product_tag.code FROM product_product_tag INNER JOIN product_tag ON product_tag.id = product_product_tag.tag_id
             WHERE product_product_tag.product_id = $0 AND product_tag.code IN ($1)',
            [
                0 => $idProduktu,
                1 => $kategorie,
            ],
        );
        if ($soucasna === [$tag]) {
            return false;
        }
        dbQuery(
            'DELETE product_product_tag FROM product_product_tag INNER JOIN product_tag ON product_tag.id = product_product_tag.tag_id
             WHERE product_product_tag.product_id = $0 AND product_tag.code IN ($1)',
            [
                0 => $idProduktu,
                1 => $kategorie,
            ],
        );
        dbQuery(
            'INSERT INTO product_product_tag (product_id, tag_id) SELECT $0, id FROM product_tag WHERE code = $1',
            [
                0 => $idProduktu,
                1 => $tag,
            ],
        );

        return true;
    }

    /**
     * Until the nights' and sizes' own catalog rows are gone, an admin edit mirrors each row's
     * state onto its variant (VariantStateMirror); the rows must agree with what was imported.
     */
    private function srovnejZbyleRadky(int $idProduktu): void
    {
        dbQuery(
            'UPDATE shop_predmety AS radek
             INNER JOIN product_variant ON product_variant.code = radek.kod_predmetu
             INNER JOIN shop_predmety AS produkt ON produkt.id_predmetu = product_variant.product_id
             SET radek.stav = product_variant.state,
                 radek.archived_at = IF(produkt.archived_at IS NULL AND product_variant.state <> $1, NULL, radek.archived_at)
             WHERE product_variant.product_id = $0 AND radek.id_predmetu <> $0',
            [
                0 => $idProduktu,
                1 => ProductStateEnum::RETIRED->value,
            ],
        );
    }

    /**
     * @param string[] $kodyProduktu
     */
    private function archivujChybejici(array $kodyProduktu): int
    {
        // Only products: a night's or size's own row is not in the sheet, its variant is.
        return dbAffectedOrNumRows(dbQuery(
            'UPDATE shop_predmety
             SET archived_at = NOW()
             WHERE archived_at IS NULL
               AND kod_predmetu NOT IN ($0)
               AND NOT EXISTS (
                   SELECT 1 FROM product_variant
                   WHERE product_variant.code = shop_predmety.kod_predmetu
                     AND product_variant.product_id <> shop_predmety.id_predmetu
               )',
            [
                0 => $kodyProduktu,
            ],
        ));
    }
}
