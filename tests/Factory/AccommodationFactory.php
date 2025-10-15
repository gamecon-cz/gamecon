<?php

namespace Gamecon\Tests\Factory;

use App\Entity\Accommodation;
use App\Repository\AccommodationRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<Accommodation>
 *
 * @method        Accommodation|Proxy create(array|callable $attributes = [])
 * @method static Accommodation|Proxy createOne(array $attributes = [])
 * @method static Accommodation|Proxy find(object|array|mixed $criteria)
 * @method static Accommodation|Proxy findOrCreate(array $attributes)
 * @method static Accommodation|Proxy first(string $sortedField = 'id')
 * @method static Accommodation|Proxy last(string $sortedField = 'id')
 * @method static Accommodation|Proxy random(array $attributes = [])
 * @method static Accommodation|Proxy randomOrCreate(array $attributes = [])
 * @method static AccommodationRepository|ProxyRepositoryDecorator repository()
 * @method static Accommodation[]|Proxy[] all()
 * @method static Accommodation[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static Accommodation[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static Accommodation[]|Proxy[] findBy(array $attributes)
 * @method static Accommodation[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static Accommodation[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class AccommodationFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Accommodation::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function defaults(): array | callable
    {
        return [
            'uzivatel' => UserFactory::new(),
            'den'      => self::faker()->numberBetween(1, 7),
            'rok'      => self::faker()->numberBetween(2020, 2030),
            'pokoj'    => self::faker()->text(50),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this// ->afterInstantiate(function(Accommodation $accommodation): void {})
            ;
    }
}
