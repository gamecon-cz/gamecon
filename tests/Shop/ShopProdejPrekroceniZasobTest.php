<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

use App\Entity\User;
use App\Service\CapacityManager;
use App\Structure\Entity\UserEntityStructure;
use Doctrine\DBAL\DriverManager;
use Gamecon\Shop\Shop;
use Gamecon\Shop\StavPredmetu;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Tests\Factory\UserFactory;

class ShopProdejPrekroceniZasobTest extends AbstractTestDb
{
    protected static bool $disableStrictTransTables = true;

    // Foundry persists via a separate Doctrine connection; running the test-class init queries
    // inside an open legacy transaction blocks Doctrine's writes (innodb auto-inc lock on
    // uzivatele_hodnoty), so we let init writes auto-commit and reset the test DB at class teardown.
    // Per-method transaction also conflicts: writes to product_product_tag via legacy PDO get
    // rolled back while Foundry's Doctrine-side insert is already committed.
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
    id_uzivatele = 88801,
    login_uzivatele = 'test_buyer_prodej',
    jmeno_uzivatele = 'Test',
    prijmeni_uzivatele = 'Buyer',
    email1_uzivatele = 'test.buyer.prodej@example.org'
SQL,
    ];

    protected static function getBeforeClassInitCallbacks(): array
    {
        return [
            static function () {
                $budouci = date('Y-m-d H:i:s', strtotime('+1 day'));

                // Limited stock item (2 pieces)
                dbQuery("INSERT INTO shop_predmety SET
                    id_predmetu = 88811,
                    nazev = 'Limitovaný předmět',
                    kod_predmetu = 'limit_prodej_test',
                    cena_aktualni = 100,
                    stav = " . StavPredmetu::VEREJNY . ",
                    nabizet_do = '{$budouci}',
                    popis = ''");
                dbQuery("INSERT INTO product_product_tag (product_id, tag_id)
                    SELECT 88811, id FROM product_tag WHERE code = 'predmet'");

                // Unlimited stock item (capacity NULL)
                dbQuery("INSERT INTO shop_predmety SET
                    id_predmetu = 88812,
                    nazev = 'Neomezený předmět',
                    kod_predmetu = 'unlim_prodej_test',
                    cena_aktualni = 100,
                    stav = " . StavPredmetu::VEREJNY . ",
                    nabizet_do = '{$budouci}',
                    popis = ''");
                dbQuery("INSERT INTO product_product_tag (product_id, tag_id)
                    SELECT 88812, id FROM product_tag WHERE code = 'predmet'");

                // Varianta ke každému prodejnému předmětu, jak to má produkce.
                dbQuery("INSERT INTO product_variant (product_id, name, code, price, capacity, position, state)
                    VALUES (88811, 'Limitovaný předmět', 'limit_prodej_test', 100, 2, 0, 1)");
                dbQuery("INSERT INTO product_variant (product_id, name, code, price, capacity, position, state)
                    VALUES (88812, 'Neomezený předmět', 'unlim_prodej_test', 100, NULL, 0, 1)");

                // Vlastní předmět pro test zápisu varianty: třída nemá rollback po metodě,
                // takže prodej z jednoho testu by ubral zásobu tomu dalšímu.
                dbQuery("INSERT INTO shop_predmety SET
                    id_predmetu = 88813,
                    nazev = 'Předmět pro variantu',
                    kod_predmetu = 'varianta_prodej_test',
                    cena_aktualni = 100,
                    stav = " . StavPredmetu::VEREJNY . ",
                    nabizet_do = '{$budouci}',
                    popis = ''");
                dbQuery("INSERT INTO product_product_tag (product_id, tag_id)
                    SELECT 88813, id FROM product_tag WHERE code = 'predmet'");
                dbQuery("INSERT INTO product_variant (product_id, name, code, price, capacity, position, state)
                    VALUES (88813, 'Předmět pro variantu', 'varianta_prodej_test', 100, 2, 0, 1)");

                dbQuery("INSERT INTO shop_predmety SET
                    id_predmetu = 88814,
                    nazev = 'Předmět pro zrušení',
                    kod_predmetu = 'zruseni_prodej_test',
                    cena_aktualni = 100,
                    stav = " . StavPredmetu::VEREJNY . ",
                    nabizet_do = '{$budouci}',
                    popis = ''");
                dbQuery("INSERT INTO product_product_tag (product_id, tag_id)
                    SELECT 88814, id FROM product_tag WHERE code = 'predmet'");
                dbQuery("INSERT INTO product_variant (product_id, name, code, price, capacity, position, state)
                    VALUES (88814, 'Předmět pro zrušení', 'zruseni_prodej_test', 100, 3, 0, 1)");

                dbQuery("INSERT INTO shop_predmety SET
                    id_predmetu = 88815,
                    nazev = 'Předmět pro souběh',
                    kod_predmetu = 'soubeh_prodej_test',
                    cena_aktualni = 100,
                    stav = " . StavPredmetu::VEREJNY . ",
                    nabizet_do = '{$budouci}',
                    popis = ''");
                dbQuery("INSERT INTO product_product_tag (product_id, tag_id)
                    SELECT 88815, id FROM product_tag WHERE code = 'predmet'");
                dbQuery("INSERT INTO product_variant (product_id, name, code, price, capacity, position, state)
                    VALUES (88815, 'Předmět pro souběh', 'soubeh_prodej_test', 100, 5, 0, 1)");

                // Room type owning its nights: no variant carries the type's own code.
                dbQuery("INSERT INTO shop_predmety SET
                    id_predmetu = 88816,
                    nazev = 'Postel na pokoji',
                    kod_predmetu = 'pokoj_prodej_test-typ',
                    cena_aktualni = 300,
                    stav = " . StavPredmetu::POZASTAVENY . ",
                    nabizet_do = '{$budouci}',
                    popis = ''");
                dbQuery("INSERT INTO shop_predmety SET
                    id_predmetu = 88817,
                    nazev = 'Postel na pokoji pátek',
                    kod_predmetu = 'pokoj_prodej_test-pa',
                    cena_aktualni = 300,
                    stav = " . StavPredmetu::VEREJNY . ",
                    nabizet_do = '{$budouci}',
                    ubytovani_den = 2,
                    popis = ''");
                dbQuery("INSERT INTO product_product_tag (product_id, tag_id)
                    SELECT ubytovani.id_predmetu, product_tag.id
                    FROM product_tag
                    INNER JOIN (SELECT 88816 AS id_predmetu UNION SELECT 88817) AS ubytovani
                    WHERE product_tag.code = 'ubytovani'");
                dbQuery("INSERT INTO product_variant (product_id, name, code, price, capacity, accommodation_day, position, state)
                    VALUES (88816, 'pátek', 'pokoj_prodej_test-pa', 300, 3, 2, 0, 1)");

                // Room type that got a default variant of its own, as a fresh import gives one.
                dbQuery("INSERT INTO shop_predmety SET
                    id_predmetu = 88818,
                    nazev = 'Postel na jiném pokoji',
                    kod_predmetu = 'pokoj_s_variantou_test-typ',
                    cena_aktualni = 300,
                    stav = " . StavPredmetu::POZASTAVENY . ",
                    nabizet_do = '{$budouci}',
                    popis = ''");
                dbQuery("INSERT INTO product_product_tag (product_id, tag_id)
                    SELECT 88818, id FROM product_tag WHERE code = 'ubytovani'");
                dbQuery("INSERT INTO product_variant (product_id, name, code, price, accommodation_day, position, state)
                    VALUES (88818, 'Postel na jiném pokoji', 'pokoj_s_variantou_test-typ', 300, NULL, 0, 3)");
            },
        ];
    }

    /**
     * Prodej z adminu musí zapsat variantu, jinak vzniká nákup, který nová vrstva nevidí
     * a do zásoby varianty se nezapočítá.
     *
     * @test
     */
    public function prodejZapiseVariantuAUbereZeZasoby(): void
    {
        $uzivatel = \Uzivatel::zIdUrcite(88801);
        $shop = new Shop($uzivatel, $uzivatel, SystemoveNastaveni::zGlobals());

        $shop->prodat(88813, 1);

        self::assertSame(
            1,
            (int) dbOneCol('SELECT COUNT(*) FROM shop_nakupy WHERE id_predmetu = 88813 AND variant_id IS NOT NULL'),
            'Nákup musí ukazovat na variantu',
        );
        self::assertSame(
            1,
            $this->zbyva('varianta_prodej_test'),
            'Ze zásoby na variantě se měl ubrat jeden kus',
        );
    }

    /**
     * Zrušení nákupu musí kus vrátit do zásoby, jinak by každá oprava v adminu zásobu
     * natrvalo snížila.
     *
     * @test
     */
    public function zruseniNakupuVratiKusDoZasoby(): void
    {
        $uzivatel = \Uzivatel::zIdUrcite(88801);
        $shop = new Shop($uzivatel, $uzivatel, SystemoveNastaveni::zGlobals());
        $zbyva = fn (): ?int => $this->zbyva('zruseni_prodej_test');

        $shop->prodat(88814, 2);
        self::assertSame(1, $zbyva(), 'Prodej měl ubrat dva kusy');

        $shop->zrusNakupPredmetu(88814, 2);

        self::assertSame(3, $zbyva(), 'Zrušení mělo oba kusy vrátit');
    }

    /**
     * @test
     */
    public function prodejNeprekrociSkladovouZasobu(): void
    {
        $uzivatel = \Uzivatel::zIdUrcite(88801);
        $shop = new Shop($uzivatel, $uzivatel, SystemoveNastaveni::zGlobals());

        // Item has only 2 pieces available (kusuVyrobeno = 2)
        // Try to sell 3 pieces - should fail
        $this->expectException(\Chyba::class);
        $shop->prodat(88811, 3);
    }

    /**
     * @test
     */
    public function prodejPovoliNakupAzDoLimituZasob(): void
    {
        $uzivatel = \Uzivatel::zIdUrcite(88801);
        $shop = new Shop($uzivatel, $uzivatel, SystemoveNastaveni::zGlobals());

        // Item has 2 pieces available
        // Selling exactly 2 should succeed
        $shop->prodat(88811, 2);

        $pocetNakupu = (int) dbOneCol(
            'SELECT COUNT(*) FROM shop_nakupy WHERE id_predmetu = $0 AND rok = $1',
            [
                0 => 88811,
                1 => ROCNIK,
            ],
        );

        self::assertSame(2, $pocetNakupu);
    }

    /**
     * @test
     */
    public function prodejPovoliNeomezenyNakupPriNullZasobach(): void
    {
        $uzivatel = \Uzivatel::zIdUrcite(88801);
        $shop = new Shop($uzivatel, $uzivatel, SystemoveNastaveni::zGlobals());

        // Item has unlimited stock (kusuVyrobeno = null)
        // Selling any amount should succeed
        $shop->prodat(88812, 100);

        $pocetNakupu = (int) dbOneCol(
            'SELECT COUNT(*) FROM shop_nakupy WHERE id_predmetu = $0 AND rok = $1',
            [
                0 => 88812,
                1 => ROCNIK,
            ],
        );

        self::assertSame(100, $pocetNakupu);
    }

    /**
     * @test
     */
    public function prodejNepovoliPredmetZJinehoRocniku(): void
    {
        $uniqueId = uniqid();

        /** @var User $user */
        $user = UserFactory::createOne([
            UserEntityStructure::login    => 'test_buyer_' . $uniqueId,
            UserEntityStructure::email    => 'test.buyer.' . $uniqueId . '@example.org',
            UserEntityStructure::jmeno    => 'Test',
            UserEntityStructure::prijmeni => 'Buyer',
        ])->_save()->_real();

        // Stejnou cestou jako ostatní fixtures v téhle třídě: přes legacy zápis, aby se
        // produkt zakládal tak, jak ho prodej opravdu čte.
        // Pohled `shop_predmety_s_typem` odvozuje `model_rok` z `archived_at`
        // (NULL → letošní ROCNIK, jinak YEAR(archived_at)), proto je tu loňský rok.
        $archivovano = (ROCNIK - 1) . '-12-31 23:59:59';
        $budouci = date('Y-m-d H:i:s', strtotime('+1 day'));
        dbQuery(
            'INSERT INTO shop_predmety SET
                nazev = $0,
                kod_predmetu = $1,
                cena_aktualni = 100,
                stav = ' . StavPredmetu::VEREJNY . ",
                nabizet_do = $2,
                popis = '',
                archived_at = $3",
            [
                0 => 'Historický předmět ' . $uniqueId,
                1 => 'HISTORY_' . strtoupper($uniqueId),
                2 => $budouci,
                3 => $archivovano,
            ],
        );
        $idPredmetu = (int) dbInsertId();
        dbQuery(
            "INSERT INTO product_product_tag (product_id, tag_id) SELECT $0, id FROM product_tag WHERE code = 'predmet'",
            [
                0 => $idPredmetu,
            ],
        );

        $uzivatel = \Uzivatel::zIdUrcite($user->getId());
        $shop = new Shop($uzivatel, $uzivatel, SystemoveNastaveni::zGlobals());

        $this->expectException(\Chyba::class);
        $this->expectExceptionMessage('nelze ho prodávat');

        $shop->prodat($idPredmetu, 1);
    }

    /**
     * A night hangs under its room type, so its variant is found by code, not by `product_id`.
     *
     * @test
     */
    public function prodejNociZapiseJejiVariantuAUbereZeZasoby(): void
    {
        $uzivatel = \Uzivatel::zIdUrcite(88801);
        $shop = new Shop($uzivatel, $uzivatel, SystemoveNastaveni::zGlobals());

        $shop->prodat(88817, 1);

        self::assertSame(
            (int) dbOneCol("SELECT id FROM product_variant WHERE code = 'pokoj_prodej_test-pa'"),
            (int) dbOneCol('SELECT variant_id FROM shop_nakupy WHERE id_predmetu = 88817'),
        );
        self::assertSame(
            2,
            $this->zbyva('pokoj_prodej_test-pa'),
        );
        self::assertSame(
            [
                'product_name' => 'Postel na pokoji pátek',
                'product_code' => 'pokoj_prodej_test-pa',
            ],
            dbOneLine('SELECT product_name, product_code FROM shop_nakupy WHERE id_predmetu = 88817'),
            'Snímek noci je z jejího vlastního řádku, jako u historických nákupů a v archivu zrušených',
        );
    }

    /**
     * A room type is not a bed on any night, so selling it would book nothing.
     *
     * @test
     *
     * @testWith [88816]
     *           [88818]
     */
    public function typPokojeNejdeProdat(int $idTypuPokoje): void
    {
        $uzivatel = \Uzivatel::zIdUrcite(88801);
        $shop = new Shop($uzivatel, $uzivatel, SystemoveNastaveni::zGlobals());

        try {
            $shop->prodat($idTypuPokoje, 1);
            self::fail('A room type must not be sellable');
        } catch (\Chyba $chyba) {
            self::assertStringContainsString('konkrétní noc', $chyba->getMessage());
        }

        self::assertSame(0, (int) dbOneCol('SELECT COUNT(*) FROM shop_nakupy WHERE id_predmetu = $0', [
            0 => $idTypuPokoje,
        ]));
    }

    /**
     * A cart sale holds the variant lock and then, inserting its purchase, a shared lock on the
     * catalog row through the foreign key. Taking that row exclusively here would deadlock with it.
     *
     * @test
     */
    public function prodejNecekaNaSdilenyZamekPredmetuOdSoubeznehoNakupu(): void
    {
        $soubeznyNakup = DriverManager::getConnection(
            static::getContainer()->get('doctrine.dbal.default_connection')->getParams(),
        );
        $soubeznyNakup->beginTransaction();
        $soubeznyNakup->fetchOne('SELECT id_predmetu FROM shop_predmety WHERE id_predmetu = 88815 LOCK IN SHARE MODE');

        $uzivatel = \Uzivatel::zIdUrcite(88801);
        $shop = new Shop($uzivatel, $uzivatel, SystemoveNastaveni::zGlobals());
        dbQuery('SET SESSION innodb_lock_wait_timeout = 1');
        try {
            $shop->prodat(88815, 1);
        } finally {
            dbQuery('SET SESSION innodb_lock_wait_timeout = DEFAULT');
            $soubeznyNakup->rollBack();
            $soubeznyNakup->close();
        }

        self::assertSame(4, $this->zbyva('soubeh_prodej_test'));
    }

    private function zbyva(string $kodVarianty): ?int
    {
        $idVarianty = (int) dbOneCol('SELECT id FROM product_variant WHERE code = $0', [
            0 => $kodVarianty,
        ]);

        return static::getContainer()->get(CapacityManager::class)->remainingByVariantId([$idVarianty])[$idVarianty] ?? null;
    }
}
