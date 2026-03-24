<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

use Gamecon\Shop\Shop;
use Gamecon\Shop\StavPredmetu;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;

class ShopProdejPrekroceniZasobTest extends AbstractTestDb
{
    protected static bool $disableStrictTransTables = true;

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
                    kusu_vyrobeno = 2,
                    popis = ''");
                dbQuery("INSERT INTO product_product_tag (product_id, tag_id)
                    SELECT 88811, id FROM product_tag WHERE code = 'predmet'");

                // Unlimited stock item (kusu_vyrobeno = NULL)
                dbQuery("INSERT INTO shop_predmety SET
                    id_predmetu = 88812,
                    nazev = 'Neomezený předmět',
                    kod_predmetu = 'unlim_prodej_test',
                    cena_aktualni = 100,
                    stav = " . StavPredmetu::VEREJNY . ",
                    nabizet_do = '{$budouci}',
                    kusu_vyrobeno = NULL,
                    popis = ''");
                dbQuery("INSERT INTO product_product_tag (product_id, tag_id)
                    SELECT 88812, id FROM product_tag WHERE code = 'predmet'");
            },
        ];
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
    public function prihlasceNeprojdeNakupPresahujiciSkladovouZasobu(): void
    {
        $uniqueId = uniqid();

        /** @var User $user */
        $user = UserFactory::createOne([
            UserEntityStructure::login    => 'test_buyer_' . $uniqueId,
            UserEntityStructure::email    => 'test.buyer.' . $uniqueId . '@example.org',
            UserEntityStructure::jmeno    => 'Test',
            UserEntityStructure::prijmeni => 'Buyer',
        ])->_real();

        /** @var ShopItem $shopItem */
        $shopItem = ShopItemFactory::createOne([
            ShopItemEntityStructure::nazev        => 'Limitovaný předmět ' . $uniqueId,
            ShopItemEntityStructure::kodPredmetu  => 'LIMIT_' . strtoupper($uniqueId),
            ShopItemEntityStructure::modelRok     => ROCNIK,
            ShopItemEntityStructure::cenaAktualni => '100',
            ShopItemEntityStructure::stav         => StavPredmetu::VEREJNY,
            ShopItemEntityStructure::nabizetDo    => new \DateTime('+1 day'),
            ShopItemEntityStructure::kusuVyrobeno => 2,
            ShopItemEntityStructure::typ          => TypPredmetu::PREDMET,
        ])->_real();

        $uzivatel = \Uzivatel::zIdUrcite($user->getId());
        $shop = new Shop($uzivatel, $uzivatel, SystemoveNastaveni::zGlobals());
        $chyba = null;

        $puvodniPost = $_POST;
        try {
            $_POST = [
                'shopP' => [
                    $shopItem->getId() => 3,
                ],
            ];

            $shop->zpracujPredmety();
        } catch (\Chyba $zachycenaChyba) {
            $chyba = $zachycenaChyba;
        } finally {
            $_POST = $puvodniPost;
        }

        self::assertInstanceOf(\Chyba::class, $chyba);
        self::assertStringContainsString('Zbývá dostupných kusů: 2', $chyba->getMessage());

        $pocetNakupu = (int) dbOneCol(<<<SQL
SELECT COUNT(*) FROM shop_nakupy WHERE id_predmetu = $0 AND rok = $1
SQL,
            [
                0 => $shopItem->getId(),
                1 => ROCNIK,
            ],
        );

        self::assertSame(0, $pocetNakupu);
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
        ])->_real();

        /** @var ShopItem $shopItem */
        $shopItem = ShopItemFactory::createOne([
            ShopItemEntityStructure::nazev        => 'Historický předmět ' . $uniqueId,
            ShopItemEntityStructure::kodPredmetu  => 'HISTORY_' . strtoupper($uniqueId),
            ShopItemEntityStructure::modelRok     => ROCNIK - 1,
            ShopItemEntityStructure::cenaAktualni => '100',
            ShopItemEntityStructure::stav         => StavPredmetu::VEREJNY,
            ShopItemEntityStructure::nabizetDo    => new \DateTime('+1 day'),
            ShopItemEntityStructure::kusuVyrobeno => 10,
            ShopItemEntityStructure::typ          => TypPredmetu::PREDMET,
        ])->_real();

        $uzivatel = \Uzivatel::zIdUrcite($user->getId());
        $shop = new Shop($uzivatel, $uzivatel, SystemoveNastaveni::zGlobals());

        $this->expectException(\Chyba::class);
        $this->expectExceptionMessage('nelze ho prodávat');

        $shop->prodat($shopItem->getId(), 1);
    }
}
