<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

use Gamecon\Shop\Shop;
use Gamecon\Shop\ShopUbytovani;
use Gamecon\Shop\TypPredmetu;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\Finance;

/**
 * Behavioral tests for legacy Shop system.
 *
 * These tests capture the business behavior of the Shop/Finance code
 * BEFORE migration away from `typ`, `model_rok`, `je_letosni_hlavni` columns.
 * After updating the legacy code to use tags/archived_at, re-run these tests
 * to verify the behavior is preserved.
 */
class ShopBehaviorTest extends AbstractTestDb
{
    protected static bool $disableStrictTransTables = true;

    protected static array $initQueries = [
        // Test user
        <<<SQL
INSERT INTO uzivatele_hodnoty SET
    id_uzivatele = 77701,
    login_uzivatele = 'shoptest',
    jmeno_uzivatele = 'Shop',
    prijmeni_uzivatele = 'Tester',
    email1_uzivatele = 'shop.tester@test.cz'
SQL,
        // Second test user (for bulk cancellation)
        <<<SQL
INSERT INTO uzivatele_hodnoty SET
    id_uzivatele = 77702,
    login_uzivatele = 'shoptest2',
    jmeno_uzivatele = 'Shop',
    prijmeni_uzivatele = 'Tester2',
    email1_uzivatele = 'shop.tester2@test.cz'
SQL,
    ];

    /**
     * Helper: insert product into shop_predmety (post-migration schema without typ/model_rok)
     * and assign a tag via product_product_tag.
     */
    private static function vlozProdukt(
        int $id,
        string $nazev,
        string $kodPredmetu,
        string $tagCode,
        float $cena,
        int $stav,
        string $nabizetDo,
        ?int $kusuVyrobeno,
        ?int $ubytovaniDen = null,
        bool $vedlejsi = false,
        string $popis = '',
        ?string $archivedAt = null,
    ): void {
        $vedlejsiInt = $vedlejsi ? 1 : 0;
        $kusuVyrobenoSql = $kusuVyrobeno === null ? 'NULL' : $kusuVyrobeno;
        $ubytovaniDenSql = $ubytovaniDen === null ? 'NULL' : $ubytovaniDen;
        $archivedAtSql = $archivedAt === null ? 'NULL' : "'{$archivedAt}'";

        dbQuery("INSERT INTO shop_predmety SET
            id_predmetu = {$id},
            nazev = '{$nazev}',
            kod_predmetu = '{$kodPredmetu}',
            cena_aktualni = {$cena},
            stav = {$stav},
            nabizet_do = '{$nabizetDo}',
            kusu_vyrobeno = {$kusuVyrobenoSql},
            ubytovani_den = {$ubytovaniDenSql},
            vedlejsi = {$vedlejsiInt},
            popis = '{$popis}',
            archived_at = {$archivedAtSql}");

        // Assign tag
        dbQuery("INSERT INTO product_product_tag (product_id, tag_id)
            SELECT {$id}, id FROM product_tag WHERE code = '{$tagCode}'");
    }

    protected static function getBeforeClassInitCallbacks(): array
    {
        return [
            static function () {
                $rocnik = ROCNIK;
                $budouci = date('Y-m-d H:i:s', strtotime('+1 year'));

                // PREDMET: hlavní
                self::vlozProdukt(77711, 'Kostka GameCon', "kostka_gc_{$rocnik}", 'predmet',
                    50, 1, $budouci, 100);

                // PREDMET: vedlejší
                self::vlozProdukt(77712, 'Zápisník', "zapisnik_{$rocnik}", 'predmet',
                    30, 1, $budouci, 50, vedlejsi: true);

                // UBYTOVANI: two days
                self::vlozProdukt(77713, 'Spacák pátek', "spacak_pa_{$rocnik}", 'ubytovani',
                    100, 1, $budouci, 200, ubytovaniDen: 1);
                self::vlozProdukt(77714, 'Spacák sobota', "spacak_so_{$rocnik}", 'ubytovani',
                    100, 1, $budouci, 200, ubytovaniDen: 2);

                // TRICKO
                self::vlozProdukt(77715, 'Tričko modré L', "tricko_modre_l_{$rocnik}", 'tricko',
                    250, 1, $budouci, 100);

                // JIDLO: two different days
                self::vlozProdukt(77716, 'Oběd pátek', "obed_pa_{$rocnik}", 'jidlo',
                    120, 1, $budouci, 500, ubytovaniDen: 1);
                self::vlozProdukt(77717, 'Večeře sobota', "vecere_so_{$rocnik}", 'jidlo',
                    130, 1, $budouci, 500, ubytovaniDen: 2);

                // VSTUPNE: early + late
                self::vlozProdukt(77718, 'Dobrovolné vstupné', "vstupne_{$rocnik}", 'vstupne',
                    0, 2, $budouci, null);
                self::vlozProdukt(77719, 'Dobrovolné vstupné pozdě', "vstupne_pozde_{$rocnik}", 'vstupne',
                    0, 2, $budouci, null);

                // PROPLACENI_BONUSU — should be skipped by constructor
                self::vlozProdukt(77720, 'Proplacení bonusu', "bonus_{$rocnik}", 'proplaceni-bonusu',
                    0, 1, $budouci, null);

                // Product from previous year — archived (for letosniPolozky year filter test)
                $minulyRocnik = $rocnik - 1;
                self::vlozProdukt(77721, 'Loňská kostka', "kostka_gc_{$minulyRocnik}", 'predmet',
                    40, 1, $budouci, 100, popis: 'stará edice',
                    archivedAt: "{$minulyRocnik}-12-31 23:59:59");

                // Purchases for user 77701 — one of each relevant type
                dbQuery("INSERT INTO shop_nakupy(id_uzivatele, id_predmetu, rok, cena_nakupni, datum)
                    VALUES (77701, 77711, {$rocnik}, 50, NOW())");  // predmet
                dbQuery("INSERT INTO shop_nakupy(id_uzivatele, id_predmetu, rok, cena_nakupni, datum)
                    VALUES (77701, 77713, {$rocnik}, 100, NOW())"); // ubytovani
                dbQuery("INSERT INTO shop_nakupy(id_uzivatele, id_predmetu, rok, cena_nakupni, datum)
                    VALUES (77701, 77715, {$rocnik}, 250, NOW())"); // tricko
                dbQuery("INSERT INTO shop_nakupy(id_uzivatele, id_predmetu, rok, cena_nakupni, datum)
                    VALUES (77701, 77716, {$rocnik}, 120, NOW())"); // jidlo
                dbQuery("INSERT INTO shop_nakupy(id_uzivatele, id_predmetu, rok, cena_nakupni, datum)
                    VALUES (77701, 77718, {$rocnik}, 200, NOW())"); // vstupne

                // Purchases for user 77702 — predmet + jidlo (for bulk cancellation test)
                dbQuery("INSERT INTO shop_nakupy(id_uzivatele, id_predmetu, rok, cena_nakupni, datum)
                    VALUES (77702, 77711, {$rocnik}, 50, NOW())");  // predmet
                dbQuery("INSERT INTO shop_nakupy(id_uzivatele, id_predmetu, rok, cena_nakupni, datum)
                    VALUES (77702, 77716, {$rocnik}, 120, NOW())"); // jidlo
            },
        ];
    }

    private function dejShopProUzivatele(int $idUzivatele): Shop
    {
        $uzivatel = \Uzivatel::zIdUrcite($idUzivatele);

        return new Shop(
            $uzivatel,
            $uzivatel,
            SystemoveNastaveni::zGlobals(),
        );
    }

    // ==================== A. Constructor product grouping ====================

    /**
     * @test
     */
    public function shopKonstruktorSeskupiProduktyPodleTypu(): void
    {
        $shop = $this->dejShopProUzivatele(77701);

        // User bought a PREDMET → should be detected
        self::assertTrue($shop->koupilNejakyPredmet(), 'Uživatel koupil předmět');

        // User bought a TRICKO → should be detected
        self::assertTrue($shop->koupilNejakeTricko(), 'Uživatel koupil tričko');

        // User bought JIDLO → should be detected
        self::assertTrue($shop->objednalNejakeJidlo(), 'Uživatel objednal jídlo');

        // UBYTOVANI → ShopUbytovani instance exists
        self::assertInstanceOf(ShopUbytovani::class, $shop->ubytovani());

        // User bought UBYTOVANI → should be detected
        self::assertTrue(
            $shop->ubytovani()->maObjednaneUbytovani(),
            'Uživatel má objednané ubytování',
        );

        // User bought something → koupilNejakouVec
        self::assertTrue($shop->koupilNejakouVec(), 'Uživatel koupil nějakou věc');
    }

    /**
     * @test
     */
    public function shopKonstruktorPreskociProplaceniBonusu(): void
    {
        // Product typ=7 exists, user has no purchase of it — constructor should not throw
        $shop = $this->dejShopProUzivatele(77701);

        // The shop should work fine — PROPLACENI_BONUSU is silently skipped
        self::assertTrue($shop->koupilNejakyPredmet());
    }

    /**
     * @test
     */
    public function shopRozdeliPredmetyNaHlavniAVedlejsi(): void
    {
        $shop = $this->dejShopProUzivatele(77701);

        // Use reflection to check private arrays — the split by vedlejsi flag
        $ref = new \ReflectionObject($shop);

        $hlavniProp = $ref->getProperty('predmetyHlavni');
        $hlavniProp->setAccessible(true);
        $hlavni = $hlavniProp->getValue($shop);

        $vedlejsiProp = $ref->getProperty('predmetyVedlejsi');
        $vedlejsiProp->setAccessible(true);
        $vedlejsi = $vedlejsiProp->getValue($shop);

        // Hlavní should contain 'Kostka GameCon' (vedlejsi=0)
        $hlavniNazvy = array_column($hlavni, 'nazev');
        self::assertContains('Kostka GameCon', $hlavniNazvy, 'Hlavní předmět je v hlavních');

        // Vedlejší should contain 'Zápisník' (vedlejsi=1)
        $vedlejsiNazvy = array_column($vedlejsi, 'nazev');
        self::assertContains('Zápisník', $vedlejsiNazvy, 'Vedlejší předmět je ve vedlejších');
    }

    /**
     * @test
     */
    public function shopVstupneRozdeleniNaVcasAPozde(): void
    {
        $shop = $this->dejShopProUzivatele(77701);

        $ref = new \ReflectionObject($shop);

        // Early vstupne (without "pozdě" in name)
        $vstupneProp = $ref->getProperty('vstupne');
        $vstupneProp->setAccessible(true);
        $vstupne = $vstupneProp->getValue($shop);
        self::assertNotEmpty($vstupne, 'Včasné vstupné existuje');
        self::assertStringNotContainsString('pozdě', $vstupne['nazev']);

        // Late vstupne (with "pozdě" in name)
        $vstupnePozdeProp = $ref->getProperty('vstupnePozde');
        $vstupnePozdeProp->setAccessible(true);
        $vstupnePozde = $vstupnePozdeProp->getValue($shop);
        self::assertNotEmpty($vstupnePozde, 'Pozdní vstupné existuje');
        self::assertStringContainsString('pozdě', $vstupnePozde['nazev']);
    }

    /**
     * @test
     */
    public function shopUzivatelBezNakupuNemaObjednaneUbytovani(): void
    {
        // User 77702 has predmet + jidlo but no ubytovani purchase
        $shop = $this->dejShopProUzivatele(77702);

        self::assertFalse(
            $shop->ubytovani()->maObjednaneUbytovani(),
            'Uživatel bez nákupu ubytování nemá objednané ubytování',
        );
    }

    // ==================== B. letosniPolozky() ====================

    /**
     * @test
     */
    public function letosniPolozkyVratiPouzePolozkyProDanyRok(): void
    {
        $polozky = Shop::letosniPolozky(ROCNIK);

        $modelRoky = array_map(
            static fn ($polozka) => $polozka->modelRok(),
            $polozky,
        );

        // All returned items should be for current year
        foreach ($modelRoky as $modelRok) {
            self::assertSame(ROCNIK, $modelRok, 'Všechny položky by měly být pro aktuální ročník');
        }

        // The previous-year item (77721) should NOT be in the result
        $idcka = array_map(
            static fn ($polozka) => $polozka->idPredmetu(),
            $polozky,
        );
        self::assertNotContains(77721, $idcka, 'Loňská položka se nesmí objevit');
    }

    /**
     * @test
     */
    public function letosniPolozkyVratiVsechnyTypy(): void
    {
        $polozky = Shop::letosniPolozky(ROCNIK);

        $typy = array_unique(array_map(
            static fn ($polozka) => $polozka->idTypu(),
            $polozky,
        ));

        sort($typy);

        // Should contain at least PREDMET, UBYTOVANI, TRICKO, JIDLO, VSTUPNE
        self::assertContains(TypPredmetu::PREDMET, $typy, 'Chybí typ PREDMET');
        self::assertContains(TypPredmetu::UBYTOVANI, $typy, 'Chybí typ UBYTOVANI');
        self::assertContains(TypPredmetu::TRICKO, $typy, 'Chybí typ TRICKO');
        self::assertContains(TypPredmetu::JIDLO, $typy, 'Chybí typ JIDLO');
        self::assertContains(TypPredmetu::VSTUPNE, $typy, 'Chybí typ VSTUPNE');
    }

    // ==================== C. zrusObjednavkyPro() ====================

    /**
     * @test
     */
    public function hromadneZruseniSmazeNakupyDanehoTypu(): void
    {
        $uzivatel = \Uzivatel::zIdUrcite(77702);

        // Verify user has both PREDMET and JIDLO purchases
        $predPredmety = (int) dbOneCol(
            'SELECT COUNT(*) FROM shop_nakupy WHERE id_uzivatele = 77702 AND id_predmetu = 77711 AND rok = $0',
            [
                0 => ROCNIK,
            ],
        );
        $predJidlo = (int) dbOneCol(
            'SELECT COUNT(*) FROM shop_nakupy WHERE id_uzivatele = 77702 AND id_predmetu = 77716 AND rok = $0',
            [
                0 => ROCNIK,
            ],
        );
        self::assertSame(1, $predPredmety, 'Předmět nákup existuje');
        self::assertSame(1, $predJidlo, 'Jídlo nákup existuje');

        // Cancel JIDLO purchases
        Shop::zrusObjednavkyPro([$uzivatel], TypPredmetu::JIDLO);

        // JIDLO purchases should be gone
        $poJidlo = (int) dbOneCol(
            'SELECT COUNT(*) FROM shop_nakupy WHERE id_uzivatele = 77702 AND id_predmetu = 77716 AND rok = $0',
            [
                0 => ROCNIK,
            ],
        );
        self::assertSame(0, $poJidlo, 'Jídlo nákupy smazány');

        // PREDMET purchases should remain
        $poPredmety = (int) dbOneCol(
            'SELECT COUNT(*) FROM shop_nakupy WHERE id_uzivatele = 77702 AND id_predmetu = 77711 AND rok = $0',
            [
                0 => ROCNIK,
            ],
        );
        self::assertSame(1, $poPredmety, 'Předmět nákupy zůstaly');
    }

    /**
     * @test
     */
    public function hromadneZruseniOdmitneNepovolenyTyp(): void
    {
        $uzivatel = \Uzivatel::zIdUrcite(77701);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Tento typ objednávek není možné hromadně zrušit');

        Shop::zrusObjednavkyPro([$uzivatel], TypPredmetu::VSTUPNE);
    }

    // ==================== D. Finance categorization ====================

    /**
     * @test
     */
    public function financeSprávněKategorizujeNákupy(): void
    {
        if (! defined('MODRE_TRICKO_ZDARMA_OD')) {
            define('MODRE_TRICKO_ZDARMA_OD', 0);
        }

        $uzivatel = \Uzivatel::zIdUrcite(77701);
        $finance = new Finance($uzivatel, 0, SystemoveNastaveni::zGlobals());

        // PREDMET (50) + TRICKO (250) → cenaPredmetu
        self::assertSame(
            round(50.0 + 250.0, 2),
            round($finance->cenaPredmetu(), 2),
            'Cena předmětů (včetně triček)',
        );

        // JIDLO (120) → cenaStravy
        self::assertSame(
            round(120.0, 2),
            round($finance->cenaStravy(), 2),
            'Cena stravy',
        );

        // UBYTOVANI (100) → cenaUbytovani
        self::assertSame(
            round(100.0, 2),
            round($finance->cenaUbytovani(), 2),
            'Cena ubytování',
        );

        // cenaPredmetyAStrava = cenaPredmetu + cenaStravy
        self::assertSame(
            round(50.0 + 250.0 + 120.0, 2),
            round($finance->cenaPredmetyAStrava(), 2),
            'Cena předmětů a stravy dohromady',
        );
    }

    // ==================== E. User.uprav chain ====================

    /**
     * @test
     */
    public function uzivatelUpravNespadneSeShopDaty(): void
    {
        $uzivatel = \Uzivatel::zIdUrcite(77701);

        // uprav() internally calls shop()->ubytovani()->maObjednaneUbytovani()
        // This must not crash even with shop data present
        $result = $uzivatel->uprav([
            'jmeno_uzivatele' => 'ShopUpravený',
        ]);

        self::assertNotNull($result);

        $reloaded = \Uzivatel::zIdUrcite(77701);
        self::assertStringContainsString('ShopUpravený', $reloaded->celeJmeno());
    }

    // ==================== F. Jídlo grouping by day ====================

    /**
     * @test
     */
    public function jidloJeSeskupenoPodleDnu(): void
    {
        $shop = $this->dejShopProUzivatele(77701);

        // Use reflection to check private jidlo array — grouped by [ubytovani_den][druh]
        $ref = new \ReflectionObject($shop);
        $jidloProp = $ref->getProperty('jidlo');
        $jidloProp->setAccessible(true);
        $jidlo = $jidloProp->getValue($shop);

        // Should have items grouped by day (ubytovani_den = 1 and 2)
        self::assertArrayHasKey('jidla', $jidlo);
        self::assertArrayHasKey(1, $jidlo['jidla'], 'Jídlo pro den 1 existuje');
        self::assertArrayHasKey(2, $jidlo['jidla'], 'Jídlo pro den 2 existuje');

        // Day 1 should contain Oběd (name without day: "Oběd")
        $druhyDen1 = array_keys($jidlo['jidla'][1]);
        self::assertNotEmpty($druhyDen1, 'Den 1 má alespoň jeden druh jídla');

        // Day 2 should contain Večeře
        $druhyDen2 = array_keys($jidlo['jidla'][2]);
        self::assertNotEmpty($druhyDen2, 'Den 2 má alespoň jeden druh jídla');
    }
}
