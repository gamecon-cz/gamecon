<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Report;

use Gamecon\Report\BfgrReport;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use OpenSpout\Reader\XLSX\Reader;

/**
 * BFGR counts a product's column from the participant's finance items, which also hold their
 * activities and payments. One stray name used to stop the export for the whole year.
 */
class BfgrParovaniPredmetuTest extends AbstractTestDb
{
    private const ID_UZIVATELE = 1253;

    protected static array $initQueries = [
        "INSERT INTO uzivatele_hodnoty SET id_uzivatele = 1253, login_uzivatele = 'BfgrParovani', jmeno_uzivatele = 'Bfgr', prijmeni_uzivatele = 'Parovani', email1_uzivatele = 'bfgr.parovani@example.invalid'",
        "INSERT INTO shop_predmety (id_predmetu, nazev, kod_predmetu, cena_aktualni, stav, popis) VALUES (125301, 'Nicknack párovací', 'nicknack_parovani', 100, 1, '')",
        // An archived die belongs to its archive year and its name already carries that year,
        // as past dice are named.
        [
            "INSERT INTO shop_predmety (id_predmetu, nazev, kod_predmetu, cena_aktualni, stav, popis, archived_at) VALUES (125302, CONCAT('Kostka párovací ', $0), 'kostka_parovani', 50, 1, '', CONCAT($0, '-12-31 00:00:00'))",
            [
                0 => ROCNIK - 1,
            ],
        ],
        "INSERT INTO product_product_tag (product_id, tag_id) SELECT id_predmetu, (SELECT id FROM product_tag WHERE code = 'predmet') FROM shop_predmety WHERE id_predmetu IN (125301, 125302)",
        "INSERT INTO product_variant (product_id, name, code, position, state) VALUES (125301, NULL, 'nicknack_parovani', 0, 1), (125302, NULL, 'kostka_parovani', 0, 1)",
        [
            "INSERT INTO shop_nakupy (id_uzivatele, variant_id, rok, cena_nakupni) SELECT $0, id, $1, 50 FROM product_variant WHERE code = 'kostka_parovani'",
            [
                0 => self::ID_UZIVATELE,
                1 => ROCNIK,
            ],
        ],
        [
            // Entered by a person, not the bank import (provedl = SYSTEM), so the note becomes the item's name.
            "INSERT INTO platby(id_uzivatele, castka, rok, provedeno, poznamka, provedl) VALUES($0, 100, $1, NOW(), 'doplatek za Nicknack párovací', $0)",
            [
                0 => self::ID_UZIVATELE,
                1 => ROCNIK,
            ],
        ],
        [
            "INSERT INTO platby(id_uzivatele, castka, rok, provedeno, poznamka, provedl) VALUES($0, -50, $1, NOW(), 'placka vrácena', $0)",
            [
                0 => self::ID_UZIVATELE,
                1 => ROCNIK,
            ],
        ],
    ];

    /**
     * @test
     */
    public function platbaSNazvemPredmetuAniKostkaSRokemVNazvuExportNeshodi(): void
    {
        $radek = $this->radekUcastnika();

        self::assertSame('0', $radek['Nicknack párovací'], 'Platba není nákup předmětu');
        self::assertSame('1', $radek['Kostka párovací ' . (ROCNIK - 1)], 'Kostka s rokem v názvu se počítá do svého sloupce');
        self::assertSame('0', $radek['Placka GC placená'], 'Vrácení peněz není koupená placka');
    }

    /**
     * @return array<string, string> the participant's BFGR row keyed by column
     */
    private function radekUcastnika(): array
    {
        $soubor = sys_get_temp_dir() . '/' . uniqid('bfgr_parovani_', true) . '.xlsx';
        (new BfgrReport(SystemoveNastaveni::zGlobals()))->exportuj('xlsx', true, $soubor);
        $reader = new Reader();
        $reader->open($soubor);
        $radky = [];
        foreach ($reader->getSheetIterator() as $list) {
            foreach ($list->getRowIterator() as $radek) {
                $radky[] = array_map(static fn ($hodnota): string => (string) $hodnota, $radek->toArray());
            }
            break;
        }
        $reader->close();
        unlink($soubor);

        // The second header row names the columns; the first only groups them.
        $hlavicka = $radky[1];
        foreach (array_slice($radky, 2) as $radek) {
            if ((int) $radek[0] === self::ID_UZIVATELE) {
                return array_combine($hlavicka, array_pad($radek, count($hlavicka), ''));
            }
        }
        self::fail('Účastník v BFGR chybí');
    }
}
