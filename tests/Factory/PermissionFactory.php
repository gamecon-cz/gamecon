<?php

namespace Gamecon\Tests\Factory;

use App\Entity\Permission;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Permission>
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
            'jmenoPrava' => self::faker()->unique()->text(255),
            'popisPrava' => self::faker()->text(),
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