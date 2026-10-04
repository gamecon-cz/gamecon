<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Report;

use Gamecon\Report\BfgrReport;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;

/**
 * Finance names a purchase after its variant ("Ponožky 42-45"); BFGR's column of that item
 * has to carry the same name, or the participant's row cannot be filled in.
 */
class BfgrVelikostiTest extends AbstractTestDb
{
    private const UZIVATEL = 449;

    protected static array $initQueries = [
        <<<SQL
INSERT INTO uzivatele_hodnoty SET id_uzivatele = 449, login_uzivatele = 'TestBfgrVelikosti', jmeno_uzivatele = 'Test', prijmeni_uzivatele = 'Velikosti', email1_uzivatele = 'test.bfgr.velikosti@example.org', pohlavi = 'f'
SQL,
        // The group owner is also the 42-45 size; 38-39 is a leftover row of the legacy layout.
        <<<SQL
INSERT INTO shop_predmety (id_predmetu, nazev, kod_predmetu, cena_aktualni, stav, popis)
VALUES (44901, 'Ponožky testovací', 'ponozky_bfgr_42-45', 150, 1, ''),
       (44902, 'Ponožky testovací (vel. 38-39)', 'ponozky_bfgr_38-39', 150, 1, '')
SQL,
        // Last year's dice has the lower id, as archived products do.
        <<<SQL
INSERT INTO shop_predmety (id_predmetu, nazev, kod_predmetu, cena_aktualni, stav, popis, archived_at)
VALUES (44911, 'Kostka testovací loňská', 'kostka_bfgr_lonska', 50, 1, '', '2025-08-01'),
       (44912, 'Kostka testovací letošní', 'kostka_bfgr_letosni', 50, 1, '', NULL),
       (44913, 'Taška testovací', 'taska_bfgr_lonska', 80, 1, '', '2025-08-01'),
       (44914, 'Taška testovací', 'taska_bfgr_letosni', 80, 1, '', NULL)
SQL,
        "INSERT INTO product_product_tag (product_id, tag_id) SELECT id_predmetu, (SELECT id FROM product_tag WHERE code = 'predmet') FROM shop_predmety WHERE id_predmetu IN (44901, 44902, 44911, 44912, 44913, 44914)",
        <<<SQL
INSERT INTO product_variant (product_id, name, code, position, state)
VALUES (44901, '42-45', 'ponozky_bfgr_42-45', 0, 1),
       (44901, '38-39', 'ponozky_bfgr_38-39', 1, 1),
       (44911, NULL, 'kostka_bfgr_lonska', 0, 1),
       (44912, NULL, 'kostka_bfgr_letosni', 0, 1),
       (44913, NULL, 'taska_bfgr_lonska', 0, 1),
       (44914, NULL, 'taska_bfgr_letosni', 0, 1)
SQL,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        // BFGR lists only who registered or bought something this year.
        dbQuery(
            'INSERT INTO shop_nakupy (id_uzivatele, variant_id, rok, cena_nakupni)
             VALUES ($0, (SELECT id FROM product_variant WHERE code = $1), $2, 150)',
            [
                0 => self::UZIVATEL,
                1 => 'ponozky_bfgr_42-45',
                2 => ROCNIK,
            ],
        );
    }

    /**
     * This year's item comes before last year's, as it always has; the configured dice order and
     * the item names tie for most of them.
     *
     * @test
     */
    public function letosniPolozkyJsouPredLonskymi(): void
    {
        $hlavicka = $this->exportuj()[1];

        foreach ([
            'Kostka testovací letošní ' . ROCNIK => 'Kostka testovací loňská 2025',
            'Taška testovací'                    => 'Taška testovací 2025',
        ] as $letosniNazev => $lonskyNazev) {
            $letosni = array_search($letosniNazev, $hlavicka, true);
            $lonska = array_search($lonskyNazev, $hlavicka, true);
            self::assertNotFalse($letosni, $letosniNazev);
            self::assertNotFalse($lonska, $lonskyNazev);
            self::assertLessThan($lonska, $letosni, $letosniNazev);
        }
    }

    /**
     * @test
     */
    public function bfgrMaSloupecProKazdouKoupenouVelikost(): void
    {
        $radky = $this->exportuj();
        $hlavicka = $radky[1];
        $ucastnik = $radky[count($radky) - 1];
        $sloupec = static function (string $nazev) use ($hlavicka): int {
            $index = array_search($nazev, $hlavicka, true);
            self::assertNotFalse($index, "Chybí sloupec {$nazev}");

            return $index;
        };

        self::assertSame('1', $ucastnik[$sloupec('Ponožky testovací 42-45')]);
        self::assertSame('0', $ucastnik[$sloupec('Ponožky testovací 38-39')]);
    }

    /**
     * @return list<list<string>> the main header, the column names, then the participant's row
     */
    private function exportuj(): array
    {
        $soubor = sys_get_temp_dir() . '/' . uniqid('BFGR_velikosti_', true) . '.csv';
        (new BfgrReport(SystemoveNastaveni::zGlobals()))->exportuj('csv', true, $soubor, self::UZIVATEL);
        $radky = array_map(
            static fn (string $radek): array => str_getcsv($radek, ';'),
            file($soubor, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES),
        );
        unlink($soubor);

        return $radky;
    }
}
