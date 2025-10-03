<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\UserRoleLog;
use App\Repository\UserRoleLogRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<UserRoleLog>
 *
 * @method        UserRoleLog|Proxy create(array|callable $attributes = [])
 * @method static UserRoleLog|Proxy createOne(array $attributes = [])
 * @method static UserRoleLog|Proxy find(object|array|mixed $criteria)
 * @method static UserRoleLog|Proxy findOrCreate(array $attributes)
 * @method static UserRoleLog|Proxy first(string $sortedField = 'id')
 * @method static UserRoleLog|Proxy last(string $sortedField = 'id')
 * @method static UserRoleLog|Proxy random(array $attributes = [])
 * @method static UserRoleLog|Proxy randomOrCreate(array $attributes = [])
 * @method static UserRoleLogRepository|ProxyRepositoryDecorator repository()
 * @method static UserRoleLog[]|Proxy[] all()
 * @method static UserRoleLog[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static UserRoleLog[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static UserRoleLog[]|Proxy[] findBy(array $attributes)
 * @method static UserRoleLog[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static UserRoleLog[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class UserRoleLogFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return UserRoleLog::class;
    }

    protected function defaults(): array
    {
        return [
            'idUzivatele' => self::faker()->numberBetween(1, 1000),
            'idRole' => self::faker()->numberBetween(1, 100),
            'idZmenil' => self::faker()->numberBetween(1, 1000),
            'zmena' => self::faker()->word(),
            'kdy' => self::faker()->dateTime(),
        ];
    }
}
