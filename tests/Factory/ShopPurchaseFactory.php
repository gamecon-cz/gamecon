<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\ShopPurchase;
use App\Repository\ShopPurchaseRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<ShopPurchase>
 *
 * @method        ShopPurchase|Proxy create(array|callable $attributes = [])
 * @method static ShopPurchase|Proxy createOne(array $attributes = [])
 * @method static ShopPurchase|Proxy find(object|array|mixed $criteria)
 * @method static ShopPurchase|Proxy findOrCreate(array $attributes)
 * @method static ShopPurchase|Proxy first(string $sortedField = 'id')
 * @method static ShopPurchase|Proxy last(string $sortedField = 'id')
 * @method static ShopPurchase|Proxy random(array $attributes = [])
 * @method static ShopPurchase|Proxy randomOrCreate(array $attributes = [])
 * @method static ShopPurchaseRepository|ProxyRepositoryDecorator repository()
 * @method static ShopPurchase[]|Proxy[] all()
 * @method static ShopPurchase[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static ShopPurchase[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static ShopPurchase[]|Proxy[] findBy(array $attributes)
 * @method static ShopPurchase[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static ShopPurchase[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class ShopPurchaseFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ShopPurchase::class;
    }

    protected function defaults(): array
    {
        return [
            'idUzivatele' => self::faker()->numberBetween(1, 1000),
            'idPredmetu' => self::faker()->numberBetween(1, 100),
            'rok' => self::faker()->numberBetween(2020, 2030),
            'cenaNakupni' => (string) self::faker()->randomFloat(2, 0, 1000),
            'datum' => self::faker()->dateTime(),
        ];
    }
}
