<?php

namespace Gamecon\Tests\Factory;

use App\Entity\Role;
use App\Repository\RoleRepository;
use App\Structure\Entity\RoleEntityStructure;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<Role>
 *
 * @method        Role|Proxy create(array|callable $attributes = [])
 * @method static Role|Proxy createOne(array $attributes = [])
 * @method static Role|Proxy find(object|array|mixed $criteria)
 * @method static Role|Proxy findOrCreate(array $attributes)
 * @method static Role|Proxy first(string $sortedField = 'id')
 * @method static Role|Proxy last(string $sortedField = 'id')
 * @method static Role|Proxy random(array $attributes = [])
 * @method static Role|Proxy randomOrCreate(array $attributes = [])
 * @method static RoleRepository|ProxyRepositoryDecorator repository()
 * @method static Role[]|Proxy[] all()
 * @method static Role[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static Role[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static Role[]|Proxy[] findBy(array $attributes)
 * @method static Role[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static Role[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class RoleFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Role::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function defaults(): array | callable
    {
        return [
            RoleEntityStructure::id            => self::faker()->unique()->numberBetween(1, 5000),
            RoleEntityStructure::kodRole       => self::faker()->unique()->text(36),
            RoleEntityStructure::nazevRole     => self::faker()->unique()->text(255),
            RoleEntityStructure::popisRole     => self::faker()->text(),
            RoleEntityStructure::rocnikRole    => self::faker()->numberBetween(-1, 2030),
            RoleEntityStructure::typRole       => self::faker()->randomElement(['trvala', 'rocnikova', 'ucast']),
            RoleEntityStructure::vyznamRole    => self::faker()->text(48),
            RoleEntityStructure::skryta        => self::faker()->boolean(),
            RoleEntityStructure::kategorieRole => self::faker()->numberBetween(0, 1),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this// ->afterInstantiate(function(Role $role): void {})
            ;
    }
}
