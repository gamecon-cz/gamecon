<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\Activity;
use App\Repository\ActivityRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

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
            'nazevAkce' => self::faker()->words(3, true),
            'urlAkce' => self::faker()->slug(),
            'zacatek' => self::faker()->dateTime(),
            'konec' => self::faker()->dateTime(),
            'kapacita' => self::faker()->numberBetween(5, 50),
            'kapacitaF' => self::faker()->numberBetween(0, 25),
            'kapacitaM' => self::faker()->numberBetween(0, 25),
            'cena' => self::faker()->numberBetween(0, 500),
            'bezSlevy' => self::faker()->boolean(),
            'nedavaBonus' => self::faker()->boolean(),
            'typ' => self::faker()->numberBetween(1, 10),
            'rok' => self::faker()->numberBetween(2020, 2030),
            'stav' => 1,
            'teamova' => self::faker()->boolean(),
            'popis' => self::faker()->numberBetween(1, 1000),
            'opisKratky' => self::faker()->sentence(),
            'vybaveni' => self::faker()->text(),
            'probehlaKorekce' => false,
        ];
    }
}
