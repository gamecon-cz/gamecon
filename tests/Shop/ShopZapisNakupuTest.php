<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

use App\Tests\Support\SoubeznaTransakce;
use Gamecon\Shop\Shop;
use Gamecon\Shop\StavPredmetu;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;

/**
 * Admin sales and both cancellations write like the new e-shop: a purchase carries its product
 * snapshot, and after a cancellation the order matches the rows it has left.
 */
class ShopZapisNakupuTest extends AbstractTestDb
{
    private const OPERATOR = 88841;
    private const ZAKAZNIK = 88842;
    private const PREDMET = 88851;
    private const POSLEDNI_KUS = 88852;
    private const VEDLEJSI_PREDMET = 88853;

    protected static bool $disableStrictTransTables = true;

    protected static function keepTestClassDbChangesInTransaction(): bool
    {
        return false;
    }

    protected static function keepSingleTestMethodDbChangesInTransaction(): bool
    {
        return false;
    }

    protected static function resetDbAfterClass(): bool
    {
        return true;
    }

    protected static array $initQueries = [
        <<<SQL
INSERT INTO uzivatele_hodnoty SET
    id_uzivatele = 88841,
    login_uzivatele = 'test_zapis_operator',
    jmeno_uzivatele = 'Test',
    prijmeni_uzivatele = 'Operator',
    email1_uzivatele = 'test.zapis.operator@example.org',
    pohlavi = 'm'
SQL,
        <<<SQL
INSERT INTO uzivatele_hodnoty SET
    id_uzivatele = 88842,
    login_uzivatele = 'test_zapis_zakaznik',
    jmeno_uzivatele = 'Test',
    prijmeni_uzivatele = 'Zakaznik',
    email1_uzivatele = 'test.zapis.zakaznik@example.org',
    pohlavi = 'f'
SQL,
    ];

    protected static function getBeforeClassInitCallbacks(): array
    {
        return [
            static function () {
                dbQuery('INSERT INTO shop_predmety SET
                    id_predmetu = ' . self::PREDMET . ',
                    nazev = "Zapisovaná placka",
                    kod_predmetu = "zapis_nakupu_test",
                    cena_aktualni = 150,
                    stav = ' . StavPredmetu::VEREJNY . ',
                    popis = ""');
                dbQuery('INSERT INTO product_product_tag (product_id, tag_id)
                    SELECT ' . self::PREDMET . ', id FROM product_tag WHERE code = "predmet"');
                dbQuery('INSERT INTO product_variant (product_id, name, code, price, capacity, position, state)
                    VALUES (' . self::PREDMET . ', "Zapisovaná placka", "zapis_nakupu_test", 150, 10, 0, 1)');

                // Right after the first variant: no other test's rows may sit between them in the index.
                dbQuery('INSERT INTO shop_predmety SET
                    id_predmetu = ' . self::VEDLEJSI_PREDMET . ',
                    nazev = "Vedlejší placka",
                    kod_predmetu = "vedlejsi_predmet_test",
                    cena_aktualni = 150,
                    stav = ' . StavPredmetu::VEREJNY . ',
                    popis = ""');
                dbQuery('INSERT INTO product_product_tag (product_id, tag_id)
                    SELECT ' . self::VEDLEJSI_PREDMET . ', id FROM product_tag WHERE code = "predmet"');
                dbQuery('INSERT INTO product_variant (product_id, name, code, price, capacity, position, state)
                    VALUES (' . self::VEDLEJSI_PREDMET . ', "Vedlejší placka", "vedlejsi_predmet_test", 150, 10, 0, 1)');

                dbQuery('INSERT INTO shop_predmety SET
                    id_predmetu = ' . self::POSLEDNI_KUS . ',
                    nazev = "Poslední placka",
                    kod_predmetu = "posledni_kus_test",
                    cena_aktualni = 150,
                    stav = ' . StavPredmetu::VEREJNY . ',
                    popis = ""');
                dbQuery('INSERT INTO product_product_tag (product_id, tag_id)
                    SELECT ' . self::POSLEDNI_KUS . ', id FROM product_tag WHERE code = "predmet"');
                dbQuery('INSERT INTO product_variant (product_id, name, code, price, capacity, position, state)
                    VALUES (' . self::POSLEDNI_KUS . ', "Poslední placka", "posledni_kus_test", 150, 1, 0, 1)');
            },
        ];
    }

    protected function tearDown(): void
    {
        dbQuery('DELETE FROM shop_nakupy WHERE id_uzivatele IN ($0)', [
            0 => [self::ZAKAZNIK, self::OPERATOR],
        ]);
        dbQuery('DELETE FROM shop_nakupy_zrusene WHERE id_uzivatele IN ($0)', [
            0 => [self::ZAKAZNIK, self::OPERATOR],
        ]);
        parent::tearDown();
    }

    private function shop(): Shop
    {
        return new Shop(
            \Uzivatel::zIdUrcite(self::ZAKAZNIK),
            \Uzivatel::zIdUrcite(self::OPERATOR),
            SystemoveNastaveni::zGlobals(),
        );
    }

    /**
     * @return int id objednávky, kterou prodej založil
     */
    private function prodej(int $kusu): int
    {
        $this->shop()->prodat($this->idVarianty(self::PREDMET), $kusu);

        return (int) dbOneCol('SELECT MAX(id) FROM shop_order WHERE customer_id = $0', [
            0 => self::ZAKAZNIK,
        ]);
    }

    private function soucetObjednavky(int $idObjednavky): string
    {
        return (string) dbOneCol('SELECT total_price FROM shop_order WHERE id = $0', [
            0 => $idObjednavky,
        ]);
    }

    /**
     * @test
     */
    public function prodejSiPamatujeCoSeProdalo(): void
    {
        $idObjednavky = $this->prodej(1);

        $nakup = dbOneLine(
            'SELECT product_name, product_code, variant_code, id_objednatele, cena_nakupni FROM shop_nakupy WHERE order_id = $0',
            [
                0 => $idObjednavky,
            ],
        );
        self::assertSame('Zapisovaná placka', $nakup['product_name']);
        self::assertSame('zapis_nakupu_test', $nakup['product_code']);
        self::assertSame('zapis_nakupu_test', $nakup['variant_code']);
        self::assertSame((string) self::OPERATOR, (string) $nakup['id_objednatele']);
        self::assertSame('150.00', (string) $nakup['cena_nakupni'], 'Prodej z adminu účtuje ceníkovou cenu');
    }

    /**
     * @test
     */
    public function zruseniObjednavekSnizSoucetObjednavky(): void
    {
        $idObjednavky = $this->prodej(2);
        self::assertSame('300.00', $this->soucetObjednavky($idObjednavky));

        $zruseno = $this->shop()->zrusZrusitelneLetosniObjednavky('test-odhlaseni');

        self::assertSame(2, $zruseno);
        self::assertSame(0, (int) dbOneCol('SELECT COUNT(*) FROM shop_nakupy WHERE order_id = $0', [
            0 => $idObjednavky,
        ]));
        self::assertSame(2, (int) dbOneCol(
            'SELECT COUNT(*) FROM shop_nakupy_zrusene WHERE id_uzivatele = $0 AND zdroj_zruseni = $1',
            [
                0 => self::ZAKAZNIK,
                1 => 'test-odhlaseni',
            ],
        ));
        self::assertSame('0.00', $this->soucetObjednavky($idObjednavky), 'Objednávka nesmí dál tvrdit, že na ní něco je');
        self::assertSame(
            ['Zapisovaná placka'],
            array_values(array_unique(dbOneArray(
                'SELECT product_name FROM shop_nakupy_zrusene WHERE id_uzivatele = $0',
                [
                    0 => self::ZAKAZNIK,
                ],
            ))),
            'Reporty zrušených nákupů čtou název, pod kterým se prodalo',
        );
    }

    /**
     * @test
     */
    public function odebraniKusuSnizSoucetObjednavky(): void
    {
        $idObjednavky = $this->prodej(2);

        $odebrano = $this->shop()->zrusNakupVarianty((int) dbOneCol('SELECT id FROM product_variant WHERE code = $0', [
            0 => 'zapis_nakupu_test',
        ]), 1);

        self::assertSame(1, $odebrano);
        self::assertSame(1, (int) dbOneCol('SELECT COUNT(*) FROM shop_nakupy WHERE order_id = $0', [
            0 => $idObjednavky,
        ]));
        self::assertSame('150.00', $this->soucetObjednavky($idObjednavky));
        self::assertSame(0, (int) dbOneCol(
            'SELECT COUNT(*) FROM shop_nakupy_zrusene WHERE id_uzivatele = $0',
            [
                0 => self::ZAKAZNIK,
            ],
        ), 'Odebrání kusu se jako zrušený nákup nearchivuje, stejně jako dřív');
    }

    /**
     * @test
     */
    public function posledniKusSeNeprodaDvakrat(): void
    {
        $rocnik = SystemoveNastaveni::zGlobals()->rocnik();
        $idVarianty = (int) dbOneCol('SELECT id FROM product_variant WHERE code = $0', [
            0 => 'posledni_kus_test',
        ]);
        $souper = SoubeznaTransakce::spust(
            static::getContainer()->get('doctrine.dbal.default_connection'),
            [
                ['sql', "SELECT id FROM product_variant WHERE id = {$idVarianty} FOR UPDATE"],
                ['sql', 'INSERT INTO shop_nakupy (id_uzivatele, variant_id, rok, cena_nakupni, datum)
                         VALUES (' . self::OPERATOR . ", {$idVarianty}, {$rocnik}, 150, NOW())"],
                ['hlasim', 'kupuje posledni kus'],
                ['cekej', 500],
                ['potvrd', ''],
            ],
        );

        $chyba = null;
        try {
            $this->shop()->prodat($this->idVarianty(self::POSLEDNI_KUS), 1);
        } catch (\Chyba $vyprodano) {
            $chyba = $vyprodano;
        }

        self::assertSame('hotovo', $souper->dokonci());
        self::assertNotNull($chyba, 'Prodej musel počkat na souběžný nákup a uvidět, že už nic nezbývá');
        self::assertStringContainsString('Zbývá dostupných kusů: 0', $chyba->getMessage());
        self::assertSame(1, (int) dbOneCol(
            'SELECT COUNT(*) FROM shop_nakupy WHERE variant_id = $0 AND rok = $1',
            [
                0 => $idVarianty,
                1 => $rocnik,
            ],
        ));
    }

    /**
     * Neither product has a purchase this year, so both stock counts land in one index gap. A sale
     * holding that gap shared would block the other's insert while waiting on its own: a deadlock.
     *
     * @test
     */
    public function prodejeRuznychPredmetuSiNestojiVCeste(): void
    {
        $rocnik = SystemoveNastaveni::zGlobals()->rocnik();
        $idVedlejsiVarianty = (int) dbOneCol('SELECT id FROM product_variant WHERE code = $0', [
            0 => 'vedlejsi_predmet_test',
        ]);
        $souper = SoubeznaTransakce::spust(
            static::getContainer()->get('doctrine.dbal.default_connection'),
            [
                ['sql', "SELECT id FROM product_variant WHERE id = {$idVedlejsiVarianty} FOR UPDATE"],
                ['sql', "SELECT COUNT(*) FROM shop_nakupy WHERE rok = {$rocnik} AND variant_id = {$idVedlejsiVarianty} LOCK IN SHARE MODE"],
                ['hlasim', 'drzi mezeru'],
                ['cekej', 700],
                ['sql', 'INSERT INTO shop_nakupy (id_uzivatele, variant_id, rok, cena_nakupni, datum)
                         VALUES (' . self::OPERATOR . ", {$idVedlejsiVarianty}, {$rocnik}, 150, NOW())"],
                ['potvrd', ''],
            ],
            'REPEATABLE READ',
        );

        dbQuery('SET SESSION innodb_lock_wait_timeout = 3');
        try {
            $this->prodej(1);
        } finally {
            dbQuery('SET SESSION innodb_lock_wait_timeout = DEFAULT');
            $vysledekSoupere = $souper->dokonci();
        }

        self::assertSame('hotovo', $vysledekSoupere, 'Souběžný prodej jiného předmětu nesmí skončit deadlockem');
        self::assertSame(1, (int) dbOneCol('SELECT COUNT(*) FROM shop_nakupy WHERE id_uzivatele = $0', [
            0 => self::ZAKAZNIK,
        ]));
    }

    /**
     * The variant a catalog row stands for, matched by code as every purchase was.
     */
    private function idVarianty(int $idPredmetu): int
    {
        $idVarianty = (int) dbOneCol(
            'SELECT product_variant.id FROM product_variant INNER JOIN shop_predmety ON shop_predmety.kod_predmetu = product_variant.code WHERE shop_predmety.id_predmetu = $0',
            [
                0 => $idPredmetu,
            ],
        );
        self::assertNotSame(0, $idVarianty, "Předmět {$idPredmetu} nemá variantu");

        return $idVarianty;
    }
}
