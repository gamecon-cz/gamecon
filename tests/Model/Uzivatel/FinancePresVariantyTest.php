<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Uzivatel;

use Gamecon\Accounting;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\Finance;

/**
 * Purchases point at their product, a shirt model or a room type, and name the size or night
 * through their variant. What is charged and what can be cancelled is that exact item.
 */
class FinancePresVariantyTest extends AbstractTestDb
{
    private const UZIVATEL = 446;

    protected static array $initQueries = [
        <<<SQL
INSERT INTO uzivatele_hodnoty SET id_uzivatele = 446, login_uzivatele = 'TestVarianty', jmeno_uzivatele = 'Test', prijmeni_uzivatele = 'Varianty', email1_uzivatele = 'test.varianty@example.org', pohlavi = 'f'
SQL,
        // A shirt group: the owner row is the S size, L is a leftover row of the legacy layout.
        <<<SQL
INSERT INTO shop_predmety (id_predmetu, nazev, kod_predmetu, cena_aktualni, stav, popis)
VALUES (44601, 'Tričko testovací', 'tricko_varianty_S', 250, 1, ''),
       (44602, 'Tričko testovací L', 'tricko_varianty_L', 250, 1, ''),
       (44611, 'Postel testovací', 'pokoj_varianty-typ', 400, 3, ''),
       (44612, 'Postel testovací pátek', 'pokoj_varianty_pa', 400, 1, '')
SQL,
        "INSERT INTO product_product_tag (product_id, tag_id) SELECT 44601, id FROM product_tag WHERE code = 'tricko'",
        "INSERT INTO product_product_tag (product_id, tag_id) SELECT 44602, id FROM product_tag WHERE code = 'tricko'",
        "INSERT INTO product_product_tag (product_id, tag_id) SELECT 44611, id FROM product_tag WHERE code = 'ubytovani'",
        "INSERT INTO product_product_tag (product_id, tag_id) SELECT 44612, id FROM product_tag WHERE code = 'ubytovani'",
        <<<SQL
INSERT INTO product_variant (product_id, name, code, accommodation_day, position, state)
VALUES (44601, 'S', 'tricko_varianty_S', NULL, 0, 1),
       (44601, 'L', 'tricko_varianty_L', NULL, 1, 1),
       (44611, 'pátek', 'pokoj_varianty_pa', 2, 0, 1)
SQL,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            'tricko_varianty_S' => 44601,
            'tricko_varianty_L' => 44601,
            'pokoj_varianty_pa' => 44611,
        ] as $kodVarianty => $idProduktu) {
            dbQuery(
                'INSERT INTO shop_nakupy (id_uzivatele, id_predmetu, variant_id, rok, cena_nakupni)
                 VALUES ($0, $1, (SELECT id FROM product_variant WHERE code = $2), $3, (SELECT cena_aktualni FROM shop_predmety WHERE id_predmetu = $1))',
                [
                    0 => self::UZIVATEL,
                    1 => $idProduktu,
                    2 => $kodVarianty,
                    3 => ROCNIK,
                ],
            );
        }
    }

    /**
     * @test
     */
    public function kazdaVelikostJeVeVypisuZvlast(): void
    {
        $polozky = array_column($this->finance()->dejStrukturovanyPrehled(), 'pocet', 'nazev');

        self::assertSame(1, $polozky['Tričko testovací S'] ?? null);
        self::assertSame(1, $polozky['Tričko testovací L'] ?? null);
    }

    /**
     * @test
     */
    public function nocJeVeVypisuSeSvymDnem(): void
    {
        self::assertContains('Postel testovací pátek', array_column($this->finance()->dejStrukturovanyPrehled(), 'nazev'));
    }

    /**
     * @test
     */
    public function zruseniTransakceZrusiPraveTuVelikost(): void
    {
        $transakceL = null;
        foreach (Accounting::getPersonalFinance(\Uzivatel::zIdUrcite(self::UZIVATEL), false)->getTransactions() as $transakce) {
            if ($transakce->getDescription() === 'Tričko testovací L') {
                $transakceL = $transakce;
            }
        }
        self::assertNotNull($transakceL, 'Tričko L musí mít vlastní transakci');

        self::assertTrue(Accounting::cancelTransaction($transakceL->getId()));

        self::assertSame(
            ['tricko_varianty_S', 'pokoj_varianty_pa'],
            dbOneArray(
                'SELECT product_variant.code FROM shop_nakupy INNER JOIN product_variant ON product_variant.id = shop_nakupy.variant_id
                 WHERE shop_nakupy.id_uzivatele = $0 ORDER BY shop_nakupy.id_nakupu',
                [
                    0 => self::UZIVATEL,
                ],
            ),
        );
    }

    private function finance(): Finance
    {
        return new Finance(\Uzivatel::zIdUrcite(self::UZIVATEL), 0, SystemoveNastaveni::zGlobals());
    }
}
