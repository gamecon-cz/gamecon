<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\SystemSettingLog;
use App\Repository\SystemSettingLogRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<SystemSettingLog>
 *
 * @method        SystemSettingLog|Proxy create(array|callable $attributes = [])
 * @method static SystemSettingLog|Proxy createOne(array $attributes = [])
 * @method static SystemSettingLog|Proxy find(object|array|mixed $criteria)
 * @method static SystemSettingLog|Proxy findOrCreate(array $attributes)
 * @method static SystemSettingLog|Proxy first(string $sortedField = 'id')
 * @method static SystemSettingLog|Proxy last(string $sortedField = 'id')
 * @method static SystemSettingLog|Proxy random(array $attributes = [])
 * @method static SystemSettingLog|Proxy randomOrCreate(array $attributes = [])
 * @method static SystemSettingLogRepository|ProxyRepositoryDecorator repository()
 * @method static SystemSettingLog[]|Proxy[] all()
 * @method static SystemSettingLog[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static SystemSettingLog[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static SystemSettingLog[]|Proxy[] findBy(array $attributes)
 * @method static SystemSettingLog[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static SystemSettingLog[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class SystemSettingLogFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return SystemSettingLog::class;
    }

    protected function defaults(): array
    {
        return [
            'idUzivatele' => self::faker()->numberBetween(1, 1000),
            'idNastaveni' => self::faker()->numberBetween(1, 100),
            'hodnota' => self::faker()->word(),
            'vlastni' => self::faker()->boolean(),
            'kdy' => self::faker()->dateTime(),
        ];
    }
}
