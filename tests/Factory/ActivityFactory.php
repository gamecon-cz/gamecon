<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\Activity;
use App\Repository\ActivityRepository;
use Zenstruck\Foundry\LazyValue;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;
use App\Structure\Entity\ActivityEntityStructure as Structure;

/**
 * @extends PersistentProxyObjectFactory<Activity>
 *
 * @method        Activity|Proxy create(array|callable $attributes = [])
 * @method static Activity|Proxy createOne(array $attributes = [])
 * @method static Activity|Proxy find(object|array|mixed $criteria)
 * @method static Activity|Proxy findOrCreate(array $attributes)
 * @method static Activity|Proxy first(string $sortedField = 'id')
 * @method static Activity|Proxy last(string $sortedField = 'id')
 * @method static Activity|Proxy random(array $attributes = [])
 * @method static Activity|Proxy randomOrCreate(array $attributes = [])
 * @method static ActivityRepository|ProxyRepositoryDecorator repository()
 * @method static Activity[]|Proxy[] all()
 * @method static Activity[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static Activity[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static Activity[]|Proxy[] findBy(array $attributes)
 * @method static Activity[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static Activity[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class ActivityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Activity::class;
    }

    protected function defaults(): array
    {
        return [
            Structure::nazevAkce => self::faker()->words(3, true),
            Structure::urlAkce => self::faker()->slug(),
            Structure::zacatek => self::faker()->dateTime(),
            Structure::konec => self::faker()->dateTime(),
            Structure::kapacita => self::faker()->numberBetween(5, 50),
            Structure::kapacitaF => self::faker()->numberBetween(0, 25),
            Structure::kapacitaM => self::faker()->numberBetween(0, 25),
            Structure::cena => self::faker()->numberBetween(0, 500),
            Structure::bezSlevy => self::faker()->boolean(),
            Structure::nedavaBonus => self::faker()->boolean(),
            Structure::type => LazyValue::new(fn() => ActivityTypeFactory::random()),
            Structure::rok => self::faker()->numberBetween(2020, 2030),
            Structure::status => LazyValue::new(fn() => ActivityStatusFactory::random()),
            Structure::teamova => self::faker()->boolean(),
            Structure::description => LazyValue::new(fn() => TextFactory::createOne()),
            Structure::shortDescription => self::faker()->sentence(),
            Structure::vybaveni => self::faker()->text(),
            Structure::probehlaKorekce => true,
        ];
    }
}
