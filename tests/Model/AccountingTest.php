<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model;

use Gamecon\Accounting;
use Gamecon\Accounting\TransactionCategory;
use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Exceptions\NeznamyTypPredmetu;
use Gamecon\Pravo;
use Gamecon\Shop\TypPredmetu;
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
INSERT INTO shop_predmety SET id_predmetu = 55501, nazev = 'předmět', model_rok = $0, kod_predmetu = CONCAT('acc_predmet_', $0), cena_aktualni = 100, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100, typ = $1
SQL,
            [
                0 => ROCNIK,
                1 => TypPredmetu::PREDMET,
            ],
        ],
        // UBYTOVANI (id 55502)
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 55502, nazev = 'ubytování', model_rok = $0, kod_predmetu = CONCAT('acc_ubytovani_', $0), cena_aktualni = 200, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100, typ = $1, ubytovani_den = 1
SQL,
            [
                0 => ROCNIK,
                1 => TypPredmetu::UBYTOVANI,
            ],
        ],
        // TRICKO (id 55503)
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 55503, nazev = 'tričko', model_rok = $0, kod_predmetu = CONCAT('acc_tricko_', $0), cena_aktualni = 150, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100, typ = $1
SQL,
            [
                0 => ROCNIK,
                1 => TypPredmetu::TRICKO,
            ],
        ],
        // JIDLO (id 55504)
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 55504, nazev = 'jídlo', model_rok = $0, kod_predmetu = CONCAT('acc_jidlo_', $0), cena_aktualni = 80, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100, typ = $1, ubytovani_den = 1
SQL,
            [
                0 => ROCNIK,
                1 => TypPredmetu::JIDLO,
            ],
        ],
        // VSTUPNE (id 55505)
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 55505, nazev = 'vstupné', model_rok = $0, kod_predmetu = CONCAT('acc_vstupne_', $0), cena_aktualni = 300, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100, typ = $1
SQL,
            [
                0 => ROCNIK,
                1 => TypPredmetu::VSTUPNE,
            ],
        ],
        // PARCON (id 55506)
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 55506, nazev = 'parcon', model_rok = $0, kod_predmetu = CONCAT('acc_parcon_', $0), cena_aktualni = 50, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100, typ = $1
SQL,
            [
                0 => ROCNIK,
                1 => TypPredmetu::PARCON,
            ],
        ],
        // PROPLACENI_BONUSU (id 55507)
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 55507, nazev = 'proplacení bonusu', model_rok = $0, kod_predmetu = CONCAT('acc_proplaceni_', $0), cena_aktualni = 500, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100, typ = $1
SQL,
            [
                0 => ROCNIK,
                1 => TypPredmetu::PROPLACENI_BONUSU,
            ],
        ],
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

    private function vlozAktivitu(
        int $idAktivity,
        string $nazev,
        float $cena,
        int $typ = TypAktivity::PREDNASKA,
        int $stavPrihlaseni = StavPrihlaseni::PRIHLASEN_A_DORAZIL,
    ): void {
        dbQuery(
            <<<SQL
            INSERT INTO akce_seznam(
                id_akce, nazev_akce, rok, cena, typ,
                kapacita, kapacita_f, kapacita_m,
                bez_slevy, nedava_bonus, teamova,
                popis, popis_kratky, vybaveni
            )
            VALUES($0, $1, $2, $3, $4, 10, 0, 0, 0, 0, 0, '', '', '')
            SQL,
            [
                0 => $idAktivity,
                1 => $nazev,
                2 => ROCNIK,
                3 => $cena,
                4 => $typ,
            ],
        );
        dbQuery(
            'INSERT INTO akce_prihlaseni(id_akce, id_uzivatele, id_stavu_prihlaseni) VALUES($0, $1, $2)',
            [
                0 => $idAktivity,
                1 => 555,
                2 => $stavPrihlaseni,
            ],
        );
    }

    private function vlozSlevu(float $castka, string $poznamka = 'Test sleva'): void
    {
        dbQuery(
            'INSERT INTO slevy(id_uzivatele, rok, castka, poznamka) VALUES($0, $1, $2, $3)',
            [
                0 => 555,
                1 => ROCNIK,
                2 => $castka,
                3 => $poznamka,
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

    /**
     * @test
     */
    public function testZustatekZMinulychLetJeVlastniTransakce(): void
    {
        dbQuery('UPDATE uzivatele_hodnoty SET zustatek = 123 WHERE id_uzivatele = $0', [555]);

        $account = Accounting::getPersonalFinance($this->dejUzivatele(), showDiscounts: false);
        $leftover = array_values(array_filter(
            $account->getTransactions(),
            fn ($transaction) => $transaction->getCategory() === TransactionCategory::LEFTOVER_FROM_LAST_YEAR,
        ));

        self::assertCount(1, $leftover, 'Zůstatek z minulých let musí být reprezentován jednou transakcí');
        self::assertSame(123, $leftover[0]->getTotalAmount());
    }

    /**
     * @test
     */
    public function testStavFinanciOdpovidaSouctuTransakci(): void
    {
        dbQuery('UPDATE uzivatele_hodnoty SET zustatek = 123 WHERE id_uzivatele = $0', [555]);

        $account = Accounting::getPersonalFinance($this->dejUzivatele(), showDiscounts: false);

        self::assertSame(123, $account->getTotal());
        self::assertStringContainsString(
            '<tr><td><b>Zůstatek z minulých let</b></td><td><b>123</b></td></tr>',
            $account->formatForHtml(),
        );
        self::assertStringContainsString(
            '<tr><td><b>Stav financí</b></td><td><b>123</b></td></tr>',
            $account->formatForHtml(),
        );
    }

    /**
     * @test
     */
    public function testZustatekZMinulychLetNenizdvojen(): void
    {
        dbQuery('UPDATE uzivatele_hodnoty SET zustatek = -55 WHERE id_uzivatele = $0', [555]);

        $account = Accounting::getPersonalFinance($this->dejUzivatele(), showDiscounts: false);
        $html = $account->formatForHtml();

        self::assertSame(
            1,
            substr_count($html, 'Zůstatek z minulých let'),
            'Zůstatek z minulých let nesmí být uveden dvakrát (souhrnný řádek i položka se stejným popiskem)',
        );
    }

    /**
     * @test
     */
    public function testAktivitaJeVidetVTransakcich(): void
    {
        $this->vlozAktivitu(idAktivity: 55601, nazev: 'Kubb', cena: 250);

        $account = Accounting::getPersonalFinance($this->dejUzivatele(), showDiscounts: false);
        $aktivity = array_values(array_filter(
            $account->getTransactions(),
            fn ($transaction) => $transaction->getCategory() === TransactionCategory::ACTIVITY,
        ));

        self::assertCount(1, $aktivity, 'Účast na aktivitě musí být reprezentována transakcí');
        self::assertSame(-250, $aktivity[0]->getTotalAmount());
    }

    /**
     * @test
     */
    public function testAktivityZobrazujiSeVHtml(): void
    {
        $this->vlozAktivitu(idAktivity: 55602, nazev: 'Vodní bitva', cena: 100);
        $this->vlozAktivitu(idAktivity: 55603, nazev: 'Kubb', cena: 150);

        $account = Accounting::getPersonalFinance($this->dejUzivatele(), showDiscounts: false);
        $html = $account->formatForHtml(positivePrices: true);

        self::assertStringContainsString('<tr><td><b>Aktivity</b></td><td><b>250</b></td></tr>', $html);
        self::assertStringContainsString('<tr><td>Vodní bitva</td><td>100</td></tr>', $html);
        self::assertStringContainsString('<tr><td>Kubb</td><td>150</td></tr>', $html);
    }

    /**
     * @test
     */
    public function testObecnaSlevaJeVidetVManualMovements(): void
    {
        $this->vlozNakup(55501, 100);
        $this->vlozSlevu(40);

        $account = Accounting::getPersonalFinance($this->dejUzivatele(), showDiscounts: false);
        $manualMovements = array_values(array_filter(
            $account->getTransactions(),
            fn ($transaction) => $transaction->getCategory() === TransactionCategory::MANUAL_MOVEMENTS,
        ));

        self::assertCount(1, $manualMovements, 'Obecná sleva musí být reprezentována transakcí v MANUAL_MOVEMENTS');
        self::assertSame(40, $manualMovements[0]->getTotalAmount());
    }

    /**
     * @test
     */
    public function testStavFinanciSouhlasiSeSouctemTransakciVcetneAktivit(): void
    {
        $this->vlozAktivitu(idAktivity: 55604, nazev: 'Kubb', cena: 250);
        $this->vlozNakup(55501, 100);
        $this->vlozPlatbu(500);

        $account = Accounting::getPersonalFinance($this->dejUzivatele(), showDiscounts: false);

        self::assertSame(150, $account->getTotal(), 'Stav financí musí být součet všech transakcí: -250 (aktivita) -100 (předmět) +500 (platba)');
    }

    /**
     * @test
     */
    public function testFormatForHtmlSeskupiStejnePolozkyDoNasobku(): void
    {
        $this->vlozNakup(55501, 100);
        $this->vlozNakup(55501, 100);

        $html = Accounting::getPersonalFinance($this->dejUzivatele(), showDiscounts: false)->formatForHtml();

        self::assertStringContainsString('<td>předmět 2×</td><td>-200</td>', $html);
        self::assertSame(0, substr_count($html, '<td>předmět</td><td>-100</td>'));
    }

    /**
     * @test
     */
    public function testFormatForHtmlSeskupiStejneSlevyDoNasobku(): void
    {
        $this->pridelPravo(Pravo::UBYTOVANI_ZDARMA);
        $this->vlozNakup(55502, 200);
        $this->vlozNakup(55502, 200);

        $html = Accounting::getPersonalFinance($this->dejUzivatele(), showDiscounts: true)->formatForHtml();

        self::assertStringContainsString('<td>ubytování 2×</td><td>-400</td>', $html);
        self::assertStringContainsString('<td>Sleva z ubytování 2×</td><td>400</td>', $html);
    }

    /**
     * @test
     */
    public function testFormatForHtmlZahrneDobrovolneVstupneDoCelkoveCeny(): void
    {
        $this->vlozNakup(55505, 300);

        $html = Accounting::getPersonalFinance($this->dejUzivatele(), showDiscounts: false)->formatForHtml();

        self::assertStringContainsString('<td><b>Dobrovolné vstupné</b></td><td><b>-300</b></td>', $html);
        self::assertStringContainsString('<td><b>Celková cena</b></td><td><b>300</b></td>', $html);
    }

    /**
     * @test
     */
    public function testFormatForHtmlSPozitivnimiCenamiPrevratiZnamenkaUSeskupenychPolozek(): void
    {
        $this->pridelPravo(Pravo::UBYTOVANI_ZDARMA);
        $this->vlozNakup(55502, 200);
        $this->vlozNakup(55502, 200);

        $html = Accounting::getPersonalFinance($this->dejUzivatele(), showDiscounts: true)
            ->formatForHtml(positivePrices: true);

        self::assertStringContainsString('<td>ubytování 2×</td><td>400</td>', $html);
        self::assertStringContainsString('<td>Sleva z ubytování 2×</td><td>-400</td>', $html);
    }
}
