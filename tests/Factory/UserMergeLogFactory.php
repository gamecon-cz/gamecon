<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\UserMergeLog;
use App\Repository\UserMergeLogRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<UserMergeLog>
 *
 * @method        UserMergeLog|Proxy create(array|callable $attributes = [])
 * @method static UserMergeLog|Proxy createOne(array $attributes = [])
 * @method static UserMergeLog|Proxy find(object|array|mixed $criteria)
 * @method static UserMergeLog|Proxy findOrCreate(array $attributes)
 * @method static UserMergeLog|Proxy first(string $sortedField = 'id')
 * @method static UserMergeLog|Proxy last(string $sortedField = 'id')
 * @method static UserMergeLog|Proxy random(array $attributes = [])
 * @method static UserMergeLog|Proxy randomOrCreate(array $attributes = [])
 * @method static UserMergeLogRepository|ProxyRepositoryDecorator repository()
 * @method static UserMergeLog[]|Proxy[] all()
 * @method static UserMergeLog[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static UserMergeLog[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static UserMergeLog[]|Proxy[] findBy(array $attributes)
 * @method static UserMergeLog[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static UserMergeLog[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class UserMergeLogFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return UserMergeLog::class;
    }

    protected function defaults(): array
    {
        return [
            'idSmazanehoUzivatele' => self::faker()->numberBetween(1, 1000),
            'idNovehoUzivatele' => self::faker()->numberBetween(1, 1000),
            'zustatekSmazanehoPuvodne' => self::faker()->numberBetween(0, 1000),
            'zustatekNovehoPuvodne' => self::faker()->numberBetween(0, 1000),
            'emailSmazaneho' => self::faker()->email(),
            'emailNovehoPuvodne' => self::faker()->email(),
            'zustatekNovehoAktualne' => self::faker()->numberBetween(0, 2000),
            'emailNovehoAktualne' => self::faker()->email(),
            'kdy' => self::faker()->dateTime(),
        ];
    }
}
