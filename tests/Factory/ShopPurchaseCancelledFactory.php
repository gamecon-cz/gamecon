<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\ShopPurchaseCancelled;
use App\Repository\ShopPurchaseCancelledRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<ShopPurchaseCancelled>
 *
 * @method        ShopPurchaseCancelled|Proxy create(array|callable $attributes = [])
 * @method static ShopPurchaseCancelled|Proxy createOne(array $attributes = [])
 * @method static ShopPurchaseCancelled|Proxy find(object|array|mixed $criteria)
 * @method static ShopPurchaseCancelled|Proxy findOrCreate(array $attributes)
 * @method static ShopPurchaseCancelled|Proxy first(string $sortedField = 'id')
 * @method static ShopPurchaseCancelled|Proxy last(string $sortedField = 'id')
 * @method static ShopPurchaseCancelled|Proxy random(array $attributes = [])
 * @method static ShopPurchaseCancelled|Proxy randomOrCreate(array $attributes = [])
 * @method static ShopPurchaseCancelledRepository|ProxyRepositoryDecorator repository()
 * @method static ShopPurchaseCancelled[]|Proxy[] all()
 * @method static ShopPurchaseCancelled[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static ShopPurchaseCancelled[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static ShopPurchaseCancelled[]|Proxy[] findBy(array $attributes)
 * @method static ShopPurchaseCancelled[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static ShopPurchaseCancelled[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class ShopPurchaseCancelledFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ShopPurchaseCancelled::class;
    }

    protected function defaults(): array
    {
        return [
            'idNakupu' => self::faker()->numberBetween(1, 100000),
            'idUzivatele' => self::faker()->numberBetween(1, 1000),
            'idPredmetu' => self::faker()->numberBetween(1, 100),
            'rocnik' => self::faker()->numberBetween(2020, 2030),
            'cenaNakupni' => (string) self::faker()->randomFloat(2, 0, 1000),
            'datumNakupu' => self::faker()->dateTime(),
            'datumZruseni' => self::faker()->dateTime(),
            'zdrojZruseni' => self::faker()->word(),
        ];
    }
}
