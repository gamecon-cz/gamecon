<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\EventLog;
use App\Repository\EventLogRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<EventLog>
 *
 * @method        EventLog|Proxy create(array|callable $attributes = [])
 * @method static EventLog|Proxy createOne(array $attributes = [])
 * @method static EventLog|Proxy find(object|array|mixed $criteria)
 * @method static EventLog|Proxy findOrCreate(array $attributes)
 * @method static EventLog|Proxy first(string $sortedField = 'id')
 * @method static EventLog|Proxy last(string $sortedField = 'id')
 * @method static EventLog|Proxy random(array $attributes = [])
 * @method static EventLog|Proxy randomOrCreate(array $attributes = [])
 * @method static EventLogRepository|ProxyRepositoryDecorator repository()
 * @method static EventLog[]|Proxy[] all()
 * @method static EventLog[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static EventLog[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static EventLog[]|Proxy[] findBy(array $attributes)
 * @method static EventLog[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static EventLog[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class EventLogFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return EventLog::class;
    }

    protected function defaults(): array
    {
        return [
            'idLogujiciho' => self::faker()->numberBetween(1, 1000),
            'zprava' => self::faker()->sentence(),
            'metadata' => self::faker()->word(),
            'rok' => self::faker()->numberBetween(2020, 2030),
        ];
    }
}
