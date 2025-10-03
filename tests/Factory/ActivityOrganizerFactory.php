<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\ActivityOrganizer;
use App\Repository\ActivityOrganizerRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<ActivityOrganizer>
 *
 * @method        ActivityOrganizer|Proxy create(array|callable $attributes = [])
 * @method static ActivityOrganizer|Proxy createOne(array $attributes = [])
 * @method static ActivityOrganizer|Proxy find(object|array|mixed $criteria)
 * @method static ActivityOrganizer|Proxy findOrCreate(array $attributes)
 * @method static ActivityOrganizer|Proxy first(string $sortedField = 'id')
 * @method static ActivityOrganizer|Proxy last(string $sortedField = 'id')
 * @method static ActivityOrganizer|Proxy random(array $attributes = [])
 * @method static ActivityOrganizer|Proxy randomOrCreate(array $attributes = [])
 * @method static ActivityOrganizerRepository|ProxyRepositoryDecorator repository()
 * @method static ActivityOrganizer[]|Proxy[] all()
 * @method static ActivityOrganizer[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static ActivityOrganizer[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static ActivityOrganizer[]|Proxy[] findBy(array $attributes)
 * @method static ActivityOrganizer[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static ActivityOrganizer[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class ActivityOrganizerFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ActivityOrganizer::class;
    }

    protected function defaults(): array
    {
        return [
            'idAkce' => self::faker()->numberBetween(1, 1000),
            'idUzivatele' => self::faker()->numberBetween(1, 1000),
        ];
    }
}
