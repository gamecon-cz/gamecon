<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\ActivityTag;
use App\Repository\ActivityTagRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<ActivityTag>
 *
 * @method        ActivityTag|Proxy create(array|callable $attributes = [])
 * @method static ActivityTag|Proxy createOne(array $attributes = [])
 * @method static ActivityTag|Proxy find(object|array|mixed $criteria)
 * @method static ActivityTag|Proxy findOrCreate(array $attributes)
 * @method static ActivityTag|Proxy first(string $sortedField = 'id')
 * @method static ActivityTag|Proxy last(string $sortedField = 'id')
 * @method static ActivityTag|Proxy random(array $attributes = [])
 * @method static ActivityTag|Proxy randomOrCreate(array $attributes = [])
 * @method static ActivityTagRepository|ProxyRepositoryDecorator repository()
 * @method static ActivityTag[]|Proxy[] all()
 * @method static ActivityTag[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static ActivityTag[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static ActivityTag[]|Proxy[] findBy(array $attributes)
 * @method static ActivityTag[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static ActivityTag[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class ActivityTagFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ActivityTag::class;
    }

    protected function defaults(): array
    {
        return [
            'idAkce' => self::faker()->numberBetween(1, 1000),
            'idTagu' => self::faker()->numberBetween(1, 100),
        ];
    }
}
