<?php

namespace Gamecon\Tests\Factory;

use App\Entity\ShopItem;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<ShopItem>
 */
final class ShopItemFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ShopItem::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function defaults(): array | callable
    {
        return [
            'nazev'           => 'Test Předmět ' . uniqid(),
            'kodPredmetu'     => 'TEST_' . strtoupper(uniqid()),
            'modelRok'        => self::faker()->numberBetween(2020, 2030),
            'cenaAktualni'    => (string) self::faker()->randomFloat(2, 0, 9999),
            'stav'            => self::faker()->numberBetween(0, 3),
            'nabizetDo'       => null,
            'kusuVyrobeno'    => self::faker()->numberBetween(0, 1000),
            'typ'             => self::faker()->numberBetween(1, 7),
            'ubytovaniDen'    => null,
            'popis'           => self::faker()->text(200),
            'jeLetosniHlavni' => false,
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this// ->afterInstantiate(function(ShopItem $shopItem): void {})
            ;
    }
}