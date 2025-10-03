<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\ReportUsageLog;
use App\Repository\ReportUsageLogRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<ReportUsageLog>
 *
 * @method        ReportUsageLog|Proxy create(array|callable $attributes = [])
 * @method static ReportUsageLog|Proxy createOne(array $attributes = [])
 * @method static ReportUsageLog|Proxy find(object|array|mixed $criteria)
 * @method static ReportUsageLog|Proxy findOrCreate(array $attributes)
 * @method static ReportUsageLog|Proxy first(string $sortedField = 'id')
 * @method static ReportUsageLog|Proxy last(string $sortedField = 'id')
 * @method static ReportUsageLog|Proxy random(array $attributes = [])
 * @method static ReportUsageLog|Proxy randomOrCreate(array $attributes = [])
 * @method static ReportUsageLogRepository|ProxyRepositoryDecorator repository()
 * @method static ReportUsageLog[]|Proxy[] all()
 * @method static ReportUsageLog[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static ReportUsageLog[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static ReportUsageLog[]|Proxy[] findBy(array $attributes)
 * @method static ReportUsageLog[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static ReportUsageLog[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class ReportUsageLogFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ReportUsageLog::class;
    }

    protected function defaults(): array
    {
        return [
            'idReportu' => self::faker()->numberBetween(1, 100),
            'idUzivatele' => self::faker()->numberBetween(1, 1000),
            'format' => self::faker()->randomElement(['xlsx', 'html', 'pdf']),
            'casPouziti' => self::faker()->dateTime(),
            'casovaZona' => self::faker()->timezone(),
        ];
    }
}
