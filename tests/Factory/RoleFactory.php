<?php

namespace Gamecon\Tests\Factory;

use App\Entity\Role;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Role>
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
            'kodRole'       => self::faker()->unique()->text(36),
            'nazevRole'     => self::faker()->unique()->text(255),
            'popisRole'     => self::faker()->text(),
            'rocnikRole'    => self::faker()->numberBetween(-1, 2030),
            'typRole'       => self::faker()->randomElement(['trvala', 'rocnikova', 'ucast']),
            'vyznamRole'    => self::faker()->text(48),
            'skryta'        => self::faker()->boolean(),
            'kategorieRole' => self::faker()->numberBetween(0, 1),
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