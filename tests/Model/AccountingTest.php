<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model;

use Gamecon\Accounting;
use Gamecon\Accounting\TransactionCategory;
use Gamecon\Exceptions\NeznamyTypPredmetu;
use Gamecon\Pravo;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;

class AccountingTest extends AbstractTestDb
{
    protected static array $initQueries = [
        <<<SQL
INSERT INTO uzivatele_hodnoty SET id_uzivatele = 555, login_uzivatele = 'TestAccounting', jmeno_uzivatele = 'Test', prijmeni_uzivatele = 'Accounting', email1_uzivatele = 'test.accounting@example.org'
SQL,
        // PREDMET (id 55501)
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 55501, nazev = 'předmět', kod_predmetu = CONCAT('acc_predmet_', $0), cena_aktualni = 100, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100
SQL,
            [
                0 => ROCNIK,
            ],
        ],
        "INSERT INTO product_product_tag (product_id, tag_id) SELECT 55501, id FROM product_tag WHERE code = 'predmet'",
        // UBYTOVANI (id 55502)
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 55502, nazev = 'ubytování', kod_predmetu = CONCAT('acc_ubytovani_', $0), cena_aktualni = 200, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100, ubytovani_den = 1
SQL,
            [
                0 => ROCNIK,
            ],
        ],
        "INSERT INTO product_product_tag (product_id, tag_id) SELECT 55502, id FROM product_tag WHERE code = 'ubytovani'",
        // TRICKO (id 55503)
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 55503, nazev = 'tričko', kod_predmetu = CONCAT('acc_tricko_', $0), cena_aktualni = 150, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100
SQL,
            [
                0 => ROCNIK,
            ],
        ],
        "INSERT INTO product_product_tag (product_id, tag_id) SELECT 55503, id FROM product_tag WHERE code = 'tricko'",
        // JIDLO (id 55504)
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 55504, nazev = 'jídlo', kod_predmetu = CONCAT('acc_jidlo_', $0), cena_aktualni = 80, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100, ubytovani_den = 1
SQL,
            [
                0 => ROCNIK,
            ],
        ],
        "INSERT INTO product_product_tag (product_id, tag_id) SELECT 55504, id FROM product_tag WHERE code = 'jidlo'",
        // VSTUPNE (id 55505)
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 55505, nazev = 'vstupné', kod_predmetu = CONCAT('acc_vstupne_', $0), cena_aktualni = 300, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100
SQL,
            [
                0 => ROCNIK,
            ],
        ],
        "INSERT INTO product_product_tag (product_id, tag_id) SELECT 55505, id FROM product_tag WHERE code = 'vstupne'",
        // PARCON (id 55506)
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 55506, nazev = 'parcon', kod_predmetu = CONCAT('acc_parcon_', $0), cena_aktualni = 50, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100
SQL,
            [
                0 => ROCNIK,
            ],
        ],
        "INSERT INTO product_product_tag (product_id, tag_id) SELECT 55506, id FROM product_tag WHERE code = 'parcon'",
        // PROPLACENI_BONUSU (id 55507)
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 55507, nazev = 'proplacení bonusu', kod_predmetu = CONCAT('acc_proplaceni_', $0), cena_aktualni = 500, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100
SQL,
            [
                0 => ROCNIK,
            ],
        ],
        "INSERT INTO product_product_tag (product_id, tag_id) SELECT 55507, id FROM product_tag WHERE code = 'proplaceni-bonusu'",
    ];

    private function vlozPlatbu(float $castka, string $poznamka = 'Test platba'): void
    {
        dbQuery(
            'INSERT INTO platby(id_uzivatele, castka, rok, provedeno, poznamka, provedl) VALUES($0, $1, $2, NOW(), $3, $4)',
            [
                0 => 555,
                1 => $castka,
                2 => ROCNIK,
                3 => $poznamka,
                4 => 555,
            ],
        );
    }

    private function vlozNakup(int $idPredmetu, float $cenaNakupni): void
    {
        dbQuery(
            'INSERT INTO shop_nakupy(id_uzivatele, id_predmetu, rok, cena_nakupni) VALUES($0, $1, $2, $3)',
            [
                0 => 555,
                1 => $idPredmetu,
                2 => ROCNIK,
                3 => $cenaNakupni,
            ],
        );
    }

    private function pridelPravo(int $idPrava): void
    {
        $unique = uniqid('', true);
        $idRole = -random_int(100000, 999999);
        $kodRole = 'TEST_ACC_' . $idPrava . '_' . $unique;
        dbQuery(<<<SQL
INSERT IGNORE INTO r_prava_soupis(id_prava, jmeno_prava, popis_prava)
VALUES ({$idPrava}, 'test_pravo_{$idPrava}', 'test')
SQL,
        );
        dbQuery(
            "INSERT INTO role_seznam(id_role, kod_role, nazev_role, popis_role, rocnik_role, typ_role, vyznam_role) VALUES ({$idRole}, '{$kodRole}', 'Test role {$unique}', '', -1, 'trvala', '')",
        );
        dbQuery("INSERT INTO prava_role(id_role, id_prava) VALUES ({$idRole}, {$idPrava})");
        dbQuery("INSERT INTO uzivatele_role(id_uzivatele, id_role, posadil) VALUES (555, {$idRole}, 555)");
    }

    private function dejUzivatele(): \Uzivatel
    {
        return \Uzivatel::zIdUrcite(555);
    }

    /**
     * @test
     */
    public function testPrazdnyNakupVraciPrazdnyUcet(): void
    {
        $account = Accounting::getPersonalFinance($this->dejUzivatele(), showDiscounts: false);

        self::assertCount(0, $account->getTransactions());
        self::assertSame(0, $account->getTotal());
    }

    /**
     * @test
     */
    public function testPredmetBezSlevyMaJednuPolozku(): void
    {
        $this->vlozNakup(55501, 100);

        $account = Accounting::getPersonalFinance($this->dejUzivatele(), showDiscounts: false);
        $transactions = $account->getTransactions();

        self::assertCount(1, $transactions);
        self::assertSame(TransactionCategory::SHOP_ITEMS, $transactions[0]->getCategory());
        self::assertSame(-100, $transactions[0]->getTotalAmount());

        $splits = $transactions[0]->getSplits();
        self::assertCount(1, $splits);
        self::assertSame(-100, $splits[0]->getAmount());
        self::assertSame('předmět', $splits[0]->getDescription());
    }

    /**
     * @test
     */
    public function testShowDiscountsFalseUkazujeKonecnouCenu(): void
    {
        $this->pridelPravo(Pravo::UBYTOVANI_ZDARMA);
        $this->vlozNakup(55502, 200);

        $account = Accounting::getPersonalFinance($this->dejUzivatele(), showDiscounts: false);
        $transactions = $account->getTransactions();

        self::assertCount(1, $transactions);
        $splits = $transactions[0]->getSplits();
        self::assertCount(1, $splits);
        self::assertSame(0, $splits[0]->getAmount());
        self::assertSame('ubytování', $splits[0]->getDescription());
    }

    /**
     * @test
     */
    public function testShowDiscountsTrueUkazujePuvodniCenuASlevouyRadek(): void
    {
        $this->pridelPravo(Pravo::UBYTOVANI_ZDARMA);
        $this->vlozNakup(55502, 200);

        $account = Accounting::getPersonalFinance($this->dejUzivatele(), showDiscounts: true);
        $transactions = $account->getTransactions();

        self::assertCount(1, $transactions);
        $splits = $transactions[0]->getSplits();
        self::assertCount(2, $splits);
        self::assertSame(-200, $splits[0]->getAmount());
        self::assertSame('ubytování', $splits[0]->getDescription());
        self::assertSame(200, $splits[1]->getAmount());
        self::assertSame('Sleva z ubytování', $splits[1]->getDescription());
    }

    /**
     * @test
     */
    public function testShowDiscountsTrueBezSlevyNemaRadekSlevy(): void
    {
        $this->vlozNakup(55501, 100);

        $account = Accounting::getPersonalFinance($this->dejUzivatele(), showDiscounts: true);
        $transactions = $account->getTransactions();

        self::assertCount(1, $transactions);
        $splits = $transactions[0]->getSplits();
        self::assertCount(1, $splits);
        self::assertSame(-100, $splits[0]->getAmount());
    }

    /**
     * @test
     */
    public function testCelkovaSumaSeSlevouJeStejnaVObouModech(): void
    {
        $this->pridelPravo(Pravo::UBYTOVANI_ZDARMA);
        $this->vlozNakup(55501, 100);
        $this->vlozNakup(55502, 200);

        $accountWithDiscount = Accounting::getPersonalFinance($this->dejUzivatele(), showDiscounts: true);
        $accountWithoutDiscount = Accounting::getPersonalFinance($this->dejUzivatele(), showDiscounts: false);

        self::assertSame($accountWithDiscount->getTotal(), $accountWithoutDiscount->getTotal());
    }

    /**
     * @test
     */
    public function testKategorieMapovani(): void
    {
        $this->vlozNakup(55501, 100); // PREDMET → SHOP_ITEMS
        $this->vlozNakup(55502, 200); // UBYTOVANI → ACCOMMODATION
        $this->vlozNakup(55503, 150); // TRICKO → SHOP_ITEMS
        $this->vlozNakup(55504, 80);  // JIDLO → FOOD
        $this->vlozNakup(55505, 300); // VSTUPNE → VOLUNTARY_DONATION

        $account = Accounting::getPersonalFinance($this->dejUzivatele(), showDiscounts: false);
        $transactions = $account->getTransactions();

        $categories = array_map(fn ($t) => $t->getCategory(), $transactions);

        self::assertContains(TransactionCategory::SHOP_ITEMS, $categories);
        self::assertContains(TransactionCategory::ACCOMMODATION, $categories);
        self::assertContains(TransactionCategory::FOOD, $categories);
        self::assertContains(TransactionCategory::VOLUNTARY_DONATION, $categories);
    }

    /**
     * @test
     */
    public function testParconVyhodiVyjimku(): void
    {
        $this->vlozNakup(55506, 50);

        $this->expectException(NeznamyTypPredmetu::class);
        Accounting::getPersonalFinance($this->dejUzivatele(), showDiscounts: false);
    }

    /**
     * @test
     */
    public function testProplaceniBonusuJeManualMovements(): void
    {
        $this->vlozNakup(55507, 500);

        $account = Accounting::getPersonalFinance($this->dejUzivatele(), showDiscounts: false);
        $transactions = $account->getTransactions();

        self::assertCount(1, $transactions);
        self::assertSame(TransactionCategory::MANUAL_MOVEMENTS, $transactions[0]->getCategory());
    }

    /**
     * @test
     */
    public function testTransactionIdFormat(): void
    {
        $this->vlozNakup(55501, 100);

        $account = Accounting::getPersonalFinance($this->dejUzivatele(), showDiscounts: false);
        $transactions = $account->getTransactions();

        self::assertMatchesRegularExpression('/^#U\[\d+]#P\[\d+]$/', $transactions[0]->getId());
        self::assertStringContainsString('#U[555]', $transactions[0]->getId());
        self::assertStringContainsString('#P[55501]', $transactions[0]->getId());
    }

    /**
     * @test
     */
    public function testPlatbaJeVidetVPripsanychPlatbach(): void
    {
        $this->vlozPlatbu(215, 'srovnání nějakého loňského bordelu');

        $account = Accounting::getPersonalFinance($this->dejUzivatele(), showDiscounts: false);
        $transactions = $account->getTransactions();

        $manualMovements = array_filter(
            $transactions,
            fn ($transaction) => $transaction->getCategory() === TransactionCategory::MANUAL_MOVEMENTS,
        );

        self::assertNotEmpty($manualMovements, 'Připsaná platba musí být vidět v objednávkách a platbách');
        $total = array_sum(array_map(fn ($transaction) => $transaction->getTotalAmount(), $manualMovements));
        self::assertSame(215, $total);
    }

    /**
     * @test
     */
    public function testCastecnaSleva(): void
    {
        $this->pridelPravo(Pravo::JIDLO_SE_SLEVOU);
        $this->vlozNakup(55504, 80);
        $sleva = SystemoveNastaveni::zGlobals()->slevaOrguNaJidloCastka();

        $accountNoDiscounts = Accounting::getPersonalFinance($this->dejUzivatele(), showDiscounts: false);
        $splitsNo = $accountNoDiscounts->getTransactions()[0]->getSplits();
        self::assertCount(1, $splitsNo);
        self::assertSame(-(int) (80 - $sleva), $splitsNo[0]->getAmount());

        $accountWithDiscounts = Accounting::getPersonalFinance($this->dejUzivatele(), showDiscounts: true);
        $splitsWith = $accountWithDiscounts->getTransactions()[0]->getSplits();
        self::assertCount(2, $splitsWith);
        self::assertSame(-80, $splitsWith[0]->getAmount());
        self::assertSame((int) $sleva, $splitsWith[1]->getAmount());
        self::assertStringStartsWith('Sleva z ', $splitsWith[1]->getDescription());
    }

    /**
     * @test
     */
    public function testVicePredmetuGenerujeViceTransakci(): void
    {
        $this->vlozNakup(55501, 100);
        $this->vlozNakup(55501, 100);
        $this->vlozNakup(55502, 200);

        $account = Accounting::getPersonalFinance($this->dejUzivatele(), showDiscounts: false);
        $transactions = $account->getTransactions();

        self::assertCount(3, $transactions);
        self::assertSame(-400, $account->getTotal());
    }
}
