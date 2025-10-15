<?php

namespace Gamecon\Tests\Factory;

use App\Entity\ActivityType;
use App\Repository\ActivityTypeRepository;
use App\Structure\Entity\ActivityTypeEntityStructure;
use Zenstruck\Foundry\LazyValue;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<ActivityType>
 * @method        ActivityType|Proxy create(array|callable $attributes = [])
 * @method static ActivityType|Proxy createOne(array $attributes = [])
 * @method static ActivityType|Proxy find(object|array|mixed $criteria)
 * @method static ActivityType|Proxy findOrCreate(array $attributes)
 * @method static ActivityType|Proxy first(string $sortedField = 'id')
 * @method static ActivityType|Proxy last(string $sortedField = 'id')
 * @method static ActivityType|Proxy random(array $attributes = [])
 * @method static ActivityType|Proxy randomOrCreate(array $attributes = [])
 * @method static ActivityTypeRepository|ProxyRepositoryDecorator repository()
 * @method static ActivityType[]|Proxy[] all()
 * @method static ActivityType[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static ActivityType[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static ActivityType[]|Proxy[] findBy(array $attributes)
 * @method static ActivityType[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static ActivityType[]|Proxy[] randomSet(int $number, array $attributes = [])
 * /
 */
final class ActivityTypeFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ActivityType::class;
    }

    protected function defaults(): array | callable
    {
        return [
            ActivityTypeEntityStructure::id            => self::faker()->unique()->numberBetween(1, 1000),
            ActivityTypeEntityStructure::typ1p         => self::faker()->words(2, true),
            ActivityTypeEntityStructure::typ1pmn       => self::faker()->words(2, true),
            ActivityTypeEntityStructure::urlTypuMn     => self::faker()->slug(),
            ActivityTypeEntityStructure::pageAbout     => LazyValue::new(fn() => PageFactory::randomOrCreate()),
            ActivityTypeEntityStructure::poradi        => self::faker()->numberBetween(1, 10),
            ActivityTypeEntityStructure::mailNeucast   => self::faker()->boolean(20), // 20% chance
            ActivityTypeEntityStructure::popisKratky   => self::faker()->sentence(),
            ActivityTypeEntityStructure::aktivni       => true,
            ActivityTypeEntityStructure::zobrazitVMenu => true,
            ActivityTypeEntityStructure::kodTypu       => self::faker()->optional()->word(),
        ];
    }
}
