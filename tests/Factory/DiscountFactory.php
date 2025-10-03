<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\Discount;
use App\Repository\DiscountRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<Discount>
 *
 * @method        Discount|Proxy create(array|callable $attributes = [])
 * @method static Discount|Proxy createOne(array $attributes = [])
 * @method static Discount|Proxy find(object|array|mixed $criteria)
 * @method static Discount|Proxy findOrCreate(array $attributes)
 * @method static Discount|Proxy first(string $sortedField = 'id')
 * @method static Discount|Proxy last(string $sortedField = 'id')
 * @method static Discount|Proxy random(array $attributes = [])
 * @method static Discount|Proxy randomOrCreate(array $attributes = [])
 * @method static DiscountRepository|ProxyRepositoryDecorator repository()
 * @method static Discount[]|Proxy[] all()
 * @method static Discount[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static Discount[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static Discount[]|Proxy[] findBy(array $attributes)
 * @method static Discount[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static Discount[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class DiscountFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Discount::class;
    }

    protected function defaults(): array
    {
        return [
            'idUzivatele' => self::faker()->numberBetween(1, 1000),
            'castka' => (string) self::faker()->randomFloat(2, 0, 1000),
            'rok' => self::faker()->numberBetween(2020, 2030),
            'provedeno' => self::faker()->dateTime(),
            'provedl' => self::faker()->numberBetween(1, 1000),
            'poznamka' => self::faker()->text(),
        ];
    }
}
