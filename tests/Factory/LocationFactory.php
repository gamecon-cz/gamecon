<?php

namespace Gamecon\Tests\Factory;

use App\Entity\Location;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Location>
 */
final class LocationFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Location::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function defaults(): array | callable
    {
        return [
            'nazev'    => 'Testovací místnost ' . uniqid(),
            'dvere'    => 'Budova C, dveře č. ' . self::faker()->numberBetween(1, 999),
            'poznamka' => self::faker()->text(100),
            'poradi'   => self::faker()->numberBetween(1, 100),
            'rok'      => 0,
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this// ->afterInstantiate(function(Location $location): void {})
            ;
    }
}