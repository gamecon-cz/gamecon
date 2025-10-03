<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\RolePermission;
use App\Repository\RolePermissionRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<RolePermission>
 *
 * @method        RolePermission|Proxy create(array|callable $attributes = [])
 * @method static RolePermission|Proxy createOne(array $attributes = [])
 * @method static RolePermission|Proxy find(object|array|mixed $criteria)
 * @method static RolePermission|Proxy findOrCreate(array $attributes)
 * @method static RolePermission|Proxy first(string $sortedField = 'id')
 * @method static RolePermission|Proxy last(string $sortedField = 'id')
 * @method static RolePermission|Proxy random(array $attributes = [])
 * @method static RolePermission|Proxy randomOrCreate(array $attributes = [])
 * @method static RolePermissionRepository|ProxyRepositoryDecorator repository()
 * @method static RolePermission[]|Proxy[] all()
 * @method static RolePermission[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static RolePermission[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static RolePermission[]|Proxy[] findBy(array $attributes)
 * @method static RolePermission[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static RolePermission[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class RolePermissionFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return RolePermission::class;
    }

    protected function defaults(): array
    {
        return [
            'idRole' => self::faker()->numberBetween(1, 100),
            'idPrava' => self::faker()->numberBetween(1, 100),
        ];
    }
}
