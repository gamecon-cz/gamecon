<?php

namespace Gamecon\Tests\Factory;

use App\Entity\ShopItem;
use App\Repository\ShopItemRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<ShopItem>
 *
 * @method        ShopItem|Proxy create(array|callable $attributes = [])
 * @method static ShopItem|Proxy createOne(array $attributes = [])
 * @method static ShopItem|Proxy find(object|array|mixed $criteria)
 * @method static ShopItem|Proxy findOrCreate(array $attributes)
 * @method static ShopItem|Proxy first(string $sortedField = 'id')
 * @method static ShopItem|Proxy last(string $sortedField = 'id')
 * @method static ShopItem|Proxy random(array $attributes = [])
 * @method static ShopItem|Proxy randomOrCreate(array $attributes = [])
 * @method static ShopItemRepository|ProxyRepositoryDecorator repository()
 * @method static ShopItem[]|Proxy[] all()
 * @method static ShopItem[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static ShopItem[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static ShopItem[]|Proxy[] findBy(array $attributes)
 * @method static ShopItem[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static ShopItem[]|Proxy[] randomSet(int $number, array $attributes = [])
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
