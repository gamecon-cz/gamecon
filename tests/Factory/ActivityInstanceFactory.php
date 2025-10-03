<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\ActivityInstance;
use App\Repository\ActivityInstanceRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<ActivityInstance>
 *
 * @method        ActivityInstance|Proxy create(array|callable $attributes = [])
 * @method static ActivityInstance|Proxy createOne(array $attributes = [])
 * @method static ActivityInstance|Proxy find(object|array|mixed $criteria)
 * @method static ActivityInstance|Proxy findOrCreate(array $attributes)
 * @method static ActivityInstance|Proxy first(string $sortedField = 'id')
 * @method static ActivityInstance|Proxy last(string $sortedField = 'id')
 * @method static ActivityInstance|Proxy random(array $attributes = [])
 * @method static ActivityInstance|Proxy randomOrCreate(array $attributes = [])
 * @method static ActivityInstanceRepository|ProxyRepositoryDecorator repository()
 * @method static ActivityInstance[]|Proxy[] all()
 * @method static ActivityInstance[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static ActivityInstance[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static ActivityInstance[]|Proxy[] findBy(array $attributes)
 * @method static ActivityInstance[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static ActivityInstance[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class ActivityInstanceFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ActivityInstance::class;
    }

    protected function defaults(): array
    {
        return [
            'idHlavniAkce' => self::faker()->numberBetween(1, 1000),
        ];
    }
}
