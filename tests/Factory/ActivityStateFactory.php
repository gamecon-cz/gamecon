<?php

namespace Gamecon\Tests\Factory;

use App\Entity\ActivityStatus;
use App\Repository\ActivityStatusRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<ActivityStatus>
 *
 * @method        ActivityStatus|Proxy create(array|callable $attributes = [])
 * @method static ActivityStatus|Proxy createOne(array $attributes = [])
 * @method static ActivityStatus|Proxy find(object|array|mixed $criteria)
 * @method static ActivityStatus|Proxy findOrCreate(array $attributes)
 * @method static ActivityStatus|Proxy first(string $sortedField = 'id')
 * @method static ActivityStatus|Proxy last(string $sortedField = 'id')
 * @method static ActivityStatus|Proxy random(array $attributes = [])
 * @method static ActivityStatus|Proxy randomOrCreate(array $attributes = [])
 * @method static ActivityStatusRepository|ProxyRepositoryDecorator repository()
 * @method static ActivityStatus[]|Proxy[] all()
 * @method static ActivityStatus[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static ActivityStatus[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static ActivityStatus[]|Proxy[] findBy(array $attributes)
 * @method static ActivityStatus[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static ActivityStatus[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class ActivityStateFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ActivityStatus::class;
    }

    protected function defaults(): array | callable
    {
        return [
            'nazev' => self::faker()->unique()->words(2, true),
        ];
    }

    protected function initialize(): static
    {
        return $this;
    }
}
