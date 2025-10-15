<?php

namespace Gamecon\Tests\Factory;

use App\Entity\ActivityRegistrationState;
use App\Repository\ActivityRegistrationStateRepository;
use App\Structure\Entity\ActivityRegistrationStateEntityStructure;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<ActivityRegistrationState>
 * @method        ActivityRegistrationState|Proxy create(array|callable $attributes = [])
 * @method static ActivityRegistrationState|Proxy createOne(array $attributes = [])
 * @method static ActivityRegistrationState|Proxy find(object|array|mixed $criteria)
 * @method static ActivityRegistrationState|Proxy findOrCreate(array $attributes)
 * @method static ActivityRegistrationState|Proxy first(string $sortedField = 'id')
 * @method static ActivityRegistrationState|Proxy last(string $sortedField = 'id')
 * @method static ActivityRegistrationState|Proxy random(array $attributes = [])
 * @method static ActivityRegistrationState|Proxy randomOrCreate(array $attributes = [])
 * @method static ActivityRegistrationStateRepository|ProxyRepositoryDecorator repository()
 * @method static ActivityRegistrationState[]|Proxy[] all()
 * @method static ActivityRegistrationState[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static ActivityRegistrationState[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static ActivityRegistrationState[]|Proxy[] findBy(array $attributes)
 * @method static ActivityRegistrationState[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static ActivityRegistrationState[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class ActivityRegistrationStateFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ActivityRegistrationState::class;
    }

    protected function defaults(): array | callable
    {
        return [
            ActivityRegistrationStateEntityStructure::id            => self::faker()->unique()->numberBetween(1, 1000),
            ActivityRegistrationStateEntityStructure::nazev         => self::faker()->unique()->words(3, true),
            ActivityRegistrationStateEntityStructure::platbaProcent => self::faker()->randomFloat(2, 0, 100),
        ];
    }

    protected function initialize(): static
    {
        return $this;
    }
}
