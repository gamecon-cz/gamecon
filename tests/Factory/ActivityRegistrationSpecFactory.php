<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\ActivityRegistrationSpec;
use App\Repository\ActivityRegistrationSpecRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<ActivityRegistrationSpec>
 *
 * @method        ActivityRegistrationSpec|Proxy create(array|callable $attributes = [])
 * @method static ActivityRegistrationSpec|Proxy createOne(array $attributes = [])
 * @method static ActivityRegistrationSpec|Proxy find(object|array|mixed $criteria)
 * @method static ActivityRegistrationSpec|Proxy findOrCreate(array $attributes)
 * @method static ActivityRegistrationSpec|Proxy first(string $sortedField = 'id')
 * @method static ActivityRegistrationSpec|Proxy last(string $sortedField = 'id')
 * @method static ActivityRegistrationSpec|Proxy random(array $attributes = [])
 * @method static ActivityRegistrationSpec|Proxy randomOrCreate(array $attributes = [])
 * @method static ActivityRegistrationSpecRepository|ProxyRepositoryDecorator repository()
 * @method static ActivityRegistrationSpec[]|Proxy[] all()
 * @method static ActivityRegistrationSpec[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static ActivityRegistrationSpec[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static ActivityRegistrationSpec[]|Proxy[] findBy(array $attributes)
 * @method static ActivityRegistrationSpec[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static ActivityRegistrationSpec[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class ActivityRegistrationSpecFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ActivityRegistrationSpec::class;
    }

    protected function defaults(): array
    {
        return [
            'idAkce' => self::faker()->numberBetween(1, 1000),
            'idUzivatele' => self::faker()->numberBetween(1, 1000),
            'idStavuPrihlaseni' => self::faker()->numberBetween(1, 10),
        ];
    }
}
