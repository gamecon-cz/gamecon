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
        "INSERT INTO product_product_tag (product_id, tag_id) SELECT id_predmetu, (SELECT id FROM product_tag WHERE code = 'predmet') FROM shop_predmety WHERE id_predmetu IN (44901, 44902)",
        <<<SQL
INSERT INTO product_variant (product_id, name, code, position, state)
VALUES (44901, '42-45', 'ponozky_bfgr_42-45', 0, 1),
       (44901, '38-39', 'ponozky_bfgr_38-39', 1, 1)
SQL,
    ];

    /**
     * @test
     */
    public function bfgrMaSloupecProKazdouKoupenouVelikost(): void
    {
        dbQuery(
            'INSERT INTO shop_nakupy (id_uzivatele, id_predmetu, variant_id, rok, cena_nakupni)
             VALUES ($0, 44901, (SELECT id FROM product_variant WHERE code = $1), $2, 150)',
            [
                0 => self::UZIVATEL,
                1 => 'ponozky_bfgr_42-45',
                2 => ROCNIK,
            ],
        );
        $soubor = sys_get_temp_dir() . '/' . uniqid('BFGR_velikosti_', true) . '.csv';

        (new BfgrReport(SystemoveNastaveni::zGlobals()))->exportuj('csv', true, $soubor, self::UZIVATEL);

        $obsah = (string) file_get_contents($soubor);
        unlink($soubor);
        self::assertStringContainsString('Ponožky testovací 42-45', $obsah);
        self::assertStringContainsString('Ponožky testovací 38-39', $obsah);
    }
}
