<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\UserRoleByYear;
use App\Repository\UserRoleByYearRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<UserRoleByYear>
 *
 * @method        UserRoleByYear|Proxy create(array|callable $attributes = [])
 * @method static UserRoleByYear|Proxy createOne(array $attributes = [])
 * @method static UserRoleByYear|Proxy find(object|array|mixed $criteria)
 * @method static UserRoleByYear|Proxy findOrCreate(array $attributes)
 * @method static UserRoleByYear|Proxy first(string $sortedField = 'id')
 * @method static UserRoleByYear|Proxy last(string $sortedField = 'id')
 * @method static UserRoleByYear|Proxy random(array $attributes = [])
 * @method static UserRoleByYear|Proxy randomOrCreate(array $attributes = [])
 * @method static UserRoleByYearRepository|ProxyRepositoryDecorator repository()
 * @method static UserRoleByYear[]|Proxy[] all()
 * @method static UserRoleByYear[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static UserRoleByYear[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static UserRoleByYear[]|Proxy[] findBy(array $attributes)
 * @method static UserRoleByYear[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static UserRoleByYear[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class UserRoleByYearFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return UserRoleByYear::class;
    }

    protected function defaults(): array
    {
        return [
            'idUzivatele' => self::faker()->numberBetween(1, 1000),
            'idRole' => self::faker()->numberBetween(1, 100),
            'odKdy' => self::faker()->dateTime(),
            'rocnik' => self::faker()->numberBetween(2020, 2030),
        ];
    }
}
