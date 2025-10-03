<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\BulkActivityLog;
use App\Repository\BulkActivityLogRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<BulkActivityLog>
 *
 * @method        BulkActivityLog|Proxy create(array|callable $attributes = [])
 * @method static BulkActivityLog|Proxy createOne(array $attributes = [])
 * @method static BulkActivityLog|Proxy find(object|array|mixed $criteria)
 * @method static BulkActivityLog|Proxy findOrCreate(array $attributes)
 * @method static BulkActivityLog|Proxy first(string $sortedField = 'id')
 * @method static BulkActivityLog|Proxy last(string $sortedField = 'id')
 * @method static BulkActivityLog|Proxy random(array $attributes = [])
 * @method static BulkActivityLog|Proxy randomOrCreate(array $attributes = [])
 * @method static BulkActivityLogRepository|ProxyRepositoryDecorator repository()
 * @method static BulkActivityLog[]|Proxy[] all()
 * @method static BulkActivityLog[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static BulkActivityLog[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static BulkActivityLog[]|Proxy[] findBy(array $attributes)
 * @method static BulkActivityLog[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static BulkActivityLog[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class BulkActivityLogFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return BulkActivityLog::class;
    }

    protected function defaults(): array
    {
        return [
            'skupina' => self::faker()->word(),
            'akce' => self::faker()->sentence(),
            'vysledek' => self::faker()->sentence(),
            'provedl' => self::faker()->numberBetween(1, 1000),
            'kdy' => self::faker()->dateTime(),
        ];
    }
}
