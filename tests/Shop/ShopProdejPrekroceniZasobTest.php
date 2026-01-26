<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

use App\Entity\ShopItem;
use App\Entity\User;
use App\Structure\Entity\ShopItemEntityStructure;
use App\Structure\Entity\UserEntityStructure;
use Gamecon\Shop\Shop;
use Gamecon\Shop\StavPredmetu;
use Gamecon\Shop\TypPredmetu;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Tests\Factory\ShopItemFactory;
use Gamecon\Tests\Factory\UserFactory;

class ShopProdejPrekroceniZasobTest extends AbstractTestDb
{
    protected static function keepTestClassDbChangesInTransaction(): bool
    {
        return true;
    }

    /**
     * @test
     */
    public function Prodej_neprekroci_skladovou_zasobu(): void
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
        $shop     = new Shop($uzivatel, $uzivatel, SystemoveNastaveni::zGlobals());

        // Item has only 2 pieces available (kusuVyrobeno = 2)
        // Try to sell 3 pieces - should fail

        $this->expectException(\Chyba::class);
        $shop->prodat($shopItem->getId(), 3);
    }

    /**
     * @test
     */
    public function Prodej_povoli_nakup_az_do_limitu_zasob(): void
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
        $shop     = new Shop($uzivatel, $uzivatel, SystemoveNastaveni::zGlobals());

        // Item has 2 pieces available
        // Selling exactly 2 should succeed
        $shop->prodat($shopItem->getId(), 2);

        $pocetNakupu = (int) dbOneCol(<<<SQL
SELECT COUNT(*) FROM shop_nakupy WHERE id_predmetu = $0 AND rok = $1
SQL,
            [0 => $shopItem->getId(), 1 => ROCNIK],
        );

        self::assertSame(2, $pocetNakupu);
    }

    /**
     * @test
     */
    public function Prodej_povoli_neomezeny_nakup_pri_null_zasobach(): void
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
            ShopItemEntityStructure::nazev        => 'Neomezený předmět ' . $uniqueId,
            ShopItemEntityStructure::kodPredmetu  => 'UNLIM_' . strtoupper($uniqueId),
            ShopItemEntityStructure::modelRok     => ROCNIK,
            ShopItemEntityStructure::cenaAktualni => '100',
            ShopItemEntityStructure::stav         => StavPredmetu::VEREJNY,
            ShopItemEntityStructure::nabizetDo    => new \DateTime('+1 day'),
            ShopItemEntityStructure::kusuVyrobeno => null,
            ShopItemEntityStructure::typ          => TypPredmetu::PREDMET,
        ])->_real();

        $uzivatel = \Uzivatel::zIdUrcite($user->getId());
        $shop     = new Shop($uzivatel, $uzivatel, SystemoveNastaveni::zGlobals());

        // Item has unlimited stock (kusuVyrobeno = null)
        // Selling any amount should succeed
        $shop->prodat($shopItem->getId(), 100);

        $pocetNakupu = (int) dbOneCol(<<<SQL
SELECT COUNT(*) FROM shop_nakupy WHERE id_predmetu = $0 AND rok = $1
SQL,
            [0 => $shopItem->getId(), 1 => ROCNIK],
        );

        self::assertSame(100, $pocetNakupu);
    }
}
