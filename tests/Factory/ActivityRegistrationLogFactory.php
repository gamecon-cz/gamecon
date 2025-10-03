<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\ActivityRegistrationLog;
use App\Repository\ActivityRegistrationLogRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<ActivityRegistrationLog>
 *
 * @method        ActivityRegistrationLog|Proxy create(array|callable $attributes = [])
 * @method static ActivityRegistrationLog|Proxy createOne(array $attributes = [])
 * @method static ActivityRegistrationLog|Proxy find(object|array|mixed $criteria)
 * @method static ActivityRegistrationLog|Proxy findOrCreate(array $attributes)
 * @method static ActivityRegistrationLog|Proxy first(string $sortedField = 'id')
 * @method static ActivityRegistrationLog|Proxy last(string $sortedField = 'id')
 * @method static ActivityRegistrationLog|Proxy random(array $attributes = [])
 * @method static ActivityRegistrationLog|Proxy randomOrCreate(array $attributes = [])
 * @method static ActivityRegistrationLogRepository|ProxyRepositoryDecorator repository()
 * @method static ActivityRegistrationLog[]|Proxy[] all()
 * @method static ActivityRegistrationLog[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static ActivityRegistrationLog[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static ActivityRegistrationLog[]|Proxy[] findBy(array $attributes)
 * @method static ActivityRegistrationLog[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static ActivityRegistrationLog[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class ActivityRegistrationLogFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ActivityRegistrationLog::class;
    }

    protected function defaults(): array
    {
        return [
            'idAkce' => self::faker()->numberBetween(1, 1000),
            'idUzivatele' => self::faker()->numberBetween(1, 1000),
            'kdy' => self::faker()->dateTime(),
            'typ' => self::faker()->word(),
            'idZmenil' => self::faker()->numberBetween(1, 1000),
            'zdrojZmeny' => self::faker()->word(),
            'rocnik' => self::faker()->numberBetween(2020, 2030),
        ];
    }
}
