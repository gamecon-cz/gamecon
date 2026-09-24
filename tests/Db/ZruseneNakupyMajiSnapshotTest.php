<?php

declare(strict_types=1);

namespace Gamecon\Tests\Db;

/**
 * Reporty nad historií mají brát název a kód z nákupu, ne z produktu — produkt se mezitím
 * mohl přejmenovat nebo (po sloučení ročníkových duplicit) splynout s jiným. `shop_nakupy`
 * takový snapshot má, zrušené nákupy ho dlouho neměly, přestože je report odhlášených
 * neplatičů vypisuje napříč ročníky.
 */
class ZruseneNakupyMajiSnapshotTest extends AbstractTestDb
{
    /**
     * @test
     */
    public function zruseneNakupyMajiSloupceProSnapshot(): void
    {
        $sloupce = dbFetchColumn(<<<SQL
SELECT column_name
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'shop_nakupy_zrusene'
  AND column_name IN ('product_name', 'product_code')
ORDER BY column_name
SQL);

        self::assertSame(['product_code', 'product_name'], $sloupce);
    }

    /**
     * Zpětné naplnění se testuje na datech, která existovala před migrací — ta v testovací
     * databázi nejsou, protože se staví migracemi. Testuje se proto samotné pravidlo: řádek
     * bez snapshotu se z produktu doplnit umí, a doplní se tím, co produkt nese teď.
     *
     * @test
     */
    public function radekBezSnapshotuSeDoplniZProduktu(): void
    {
        dbQuery(<<<SQL
INSERT INTO shop_predmety SET id_predmetu = 94010, nazev = 'Ponožky (vel. 42-45)', kod_predmetu = 'ponozky_94010', cena_aktualni = 120, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 10
SQL);
        dbQuery(<<<SQL
INSERT INTO shop_nakupy_zrusene SET id_uzivatele = 1, id_predmetu = 94010, rocnik = 2024, cena_nakupni = 120, datum_nakupu = NOW(), datum_zruseni = NOW(), zdroj_zruseni = 'rucne-hromadne'
SQL);

        self::assertNull(
            dbFetchSingle('SELECT product_name FROM shop_nakupy_zrusene WHERE id_predmetu = $0', [
                0 => 94010,
            ]),
            'Nový řádek zatím snapshot nemá — to je stav, který migrace zastihla u historických dat',
        );

        dbQuery(<<<SQL
UPDATE shop_nakupy_zrusene
JOIN shop_predmety ON shop_predmety.id_predmetu = shop_nakupy_zrusene.id_predmetu
SET shop_nakupy_zrusene.product_name = shop_predmety.nazev,
    shop_nakupy_zrusene.product_code = shop_predmety.kod_predmetu
WHERE shop_nakupy_zrusene.product_name IS NULL
SQL);

        self::assertSame(
            'Ponožky (vel. 42-45)',
            dbFetchSingle('SELECT product_name FROM shop_nakupy_zrusene WHERE id_predmetu = $0', [
                0 => 94010,
            ]),
        );
    }

    /**
     * Velikost drží varianta, ne název produktu: seskupení triček pod jednoho vlastníka
     * mu ji z názvu ustřihlo. Přilepit ji ale jde jen tam, kde v názvu chybí — jinak
     * vznikne „Tričko účastnické XXL XXL" nebo „Placka Placka".
     *
     * @test
     *
     * @dataProvider nazvyAVarianty
     */
    public function velikostSeDoplniJenKdyzVNazvuChybi(
        string $nazevProduktu,
        ?string $nazevVarianty,
        string $ocekavano,
    ): void {
        self::assertSame($ocekavano, $this->slozNazev($nazevProduktu, $nazevVarianty));
    }

    /**
     * @return iterable<string, array{string, string|null, string}>
     */
    public static function nazvyAVarianty(): iterable
    {
        yield 'vlastník skupiny přišel o velikost' => ['Tričko účastnické', 'XXXL', 'Tričko účastnické XXXL'];
        yield 'osiřelý řádek velikost v názvu má' => ['Tričko účastnické XXL', 'XXL', 'Tričko účastnické XXL'];
        yield 'velikost v závorce uprostřed' => ['Ponožky (vel. 38-39)', '38-39', 'Ponožky (vel. 38-39)'];
        yield 'jediná varianta se jmenuje jako produkt' => ['Placka', 'Placka', 'Placka'];
        yield 'produkt bez varianty' => ['Kostka 2026', null, 'Kostka 2026'];
        // Krátká velikost se nesmí hledat jako podřetězec — „S" je i uvnitř „pánské".
        yield 'velikost S uvnitř jiného slova nestačí' => ['Tričko modré pánské', 'S', 'Tričko modré pánské S'];
        // Závorka je v regulárním výrazu metaznak, takže shodu nesmí rozhodovat REGEXP sám.
        yield 'název se závorkou se nesmí zdvojit' => ['Fate kostka (dřevěná)', 'Fate kostka (dřevěná)', 'Fate kostka (dřevěná)'];
        yield 'varianta, která není velikost, se nepřipojuje' => ['Kostka 2022 - Duna', 'Duna', 'Kostka 2022 - Duna'];
    }

    /**
     * Stejné pravidlo jako v migraci, spočítané databází — ať test neověřuje jinou
     * implementaci, než která se na data doopravdy pustí.
     */
    private function slozNazev(
        string $nazevProduktu,
        ?string $nazevVarianty,
    ): string {
        return (string) dbFetchSingle(
            <<<SQL
            SELECT IF(
                $1 IS NOT NULL
                    AND TRIM($1) REGEXP '^(XS|S|M|L|XL|XXL|XXXL|[0-9]+-[0-9]+)$'
                    AND $0 NOT REGEXP CONCAT('[[:<:]]', TRIM($1), '[[:>:]]'),
                CONCAT($0, ' ', TRIM($1)),
                $0
            )
            SQL,
            [
                0 => $nazevProduktu,
                1 => $nazevVarianty,
            ],
        );
    }
}
