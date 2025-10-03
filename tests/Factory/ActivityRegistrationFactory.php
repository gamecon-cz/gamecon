<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\ActivityRegistration;
use App\Repository\ActivityRegistrationRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<ActivityRegistration>
 *
 * @method        ActivityRegistration|Proxy create(array|callable $attributes = [])
 * @method static ActivityRegistration|Proxy createOne(array $attributes = [])
 * @method static ActivityRegistration|Proxy find(object|array|mixed $criteria)
 * @method static ActivityRegistration|Proxy findOrCreate(array $attributes)
 * @method static ActivityRegistration|Proxy first(string $sortedField = 'id')
 * @method static ActivityRegistration|Proxy last(string $sortedField = 'id')
 * @method static ActivityRegistration|Proxy random(array $attributes = [])
 * @method static ActivityRegistration|Proxy randomOrCreate(array $attributes = [])
 * @method static ActivityRegistrationRepository|ProxyRepositoryDecorator repository()
 * @method static ActivityRegistration[]|Proxy[] all()
 * @method static ActivityRegistration[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static ActivityRegistration[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static ActivityRegistration[]|Proxy[] findBy(array $attributes)
 * @method static ActivityRegistration[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static ActivityRegistration[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class ActivityRegistrationFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ActivityRegistration::class;
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
