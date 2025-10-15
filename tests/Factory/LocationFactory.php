<?php

namespace Gamecon\Tests\Factory;

use App\Entity\Location;
use App\Repository\LocationRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<Location>
 *
 * @method        Location|Proxy create(array|callable $attributes = [])
 * @method static Location|Proxy createOne(array $attributes = [])
 * @method static Location|Proxy find(object|array|mixed $criteria)
 * @method static Location|Proxy findOrCreate(array $attributes)
 * @method static Location|Proxy first(string $sortedField = 'id')
 * @method static Location|Proxy last(string $sortedField = 'id')
 * @method static Location|Proxy random(array $attributes = [])
 * @method static Location|Proxy randomOrCreate(array $attributes = [])
 * @method static LocationRepository|ProxyRepositoryDecorator repository()
 * @method static Location[]|Proxy[] all()
 * @method static Location[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static Location[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static Location[]|Proxy[] findBy(array $attributes)
 * @method static Location[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static Location[]|Proxy[] randomSet(int $number, array $attributes = [])
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
