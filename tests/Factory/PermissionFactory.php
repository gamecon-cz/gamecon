<?php

namespace Gamecon\Tests\Factory;

use App\Entity\Permission;
use App\Repository\PermissionRepository;
use App\Structure\Entity\PermissionEntityStructure;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<Permission>
 *
 * @method        Permission|Proxy create(array|callable $attributes = [])
 * @method static Permission|Proxy createOne(array $attributes = [])
 * @method static Permission|Proxy find(object|array|mixed $criteria)
 * @method static Permission|Proxy findOrCreate(array $attributes)
 * @method static Permission|Proxy first(string $sortedField = 'id')
 * @method static Permission|Proxy last(string $sortedField = 'id')
 * @method static Permission|Proxy random(array $attributes = [])
 * @method static Permission|Proxy randomOrCreate(array $attributes = [])
 * @method static PermissionRepository|ProxyRepositoryDecorator repository()
 * @method static Permission[]|Proxy[] all()
 * @method static Permission[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static Permission[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static Permission[]|Proxy[] findBy(array $attributes)
 * @method static Permission[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static Permission[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class PermissionFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Permission::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function defaults(): array | callable
    {
        return [
            PermissionEntityStructure::id => self::faker()->unique()->numberBetween(1, 100000),
            PermissionEntityStructure::jmenoPrava => self::faker()->unique()->text(255),
            PermissionEntityStructure::popisPrava => self::faker()->text(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this// ->afterInstantiate(function(Permission $permission): void {})
            ;
    }
}
