<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\ActivityLocationTemp;
use App\Repository\ActivityLocationTempRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<ActivityLocationTemp>
 *
 * @method        ActivityLocationTemp|Proxy create(array|callable $attributes = [])
 * @method static ActivityLocationTemp|Proxy createOne(array $attributes = [])
 * @method static ActivityLocationTemp|Proxy find(object|array|mixed $criteria)
 * @method static ActivityLocationTemp|Proxy findOrCreate(array $attributes)
 * @method static ActivityLocationTemp|Proxy first(string $sortedField = 'id')
 * @method static ActivityLocationTemp|Proxy last(string $sortedField = 'id')
 * @method static ActivityLocationTemp|Proxy random(array $attributes = [])
 * @method static ActivityLocationTemp|Proxy randomOrCreate(array $attributes = [])
 * @method static ActivityLocationTempRepository|ProxyRepositoryDecorator repository()
 * @method static ActivityLocationTemp[]|Proxy[] all()
 * @method static ActivityLocationTemp[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static ActivityLocationTemp[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static ActivityLocationTemp[]|Proxy[] findBy(array $attributes)
 * @method static ActivityLocationTemp[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static ActivityLocationTemp[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class ActivityLocationTempFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ActivityLocationTemp::class;
    }

    protected function defaults(): array
    {
        return [
            'nazev' => self::faker()->words(3, true),
            'dvere' => self::faker()->word(),
            'poznamka' => self::faker()->text(),
            'poradi' => self::faker()->numberBetween(1, 100),
            'rok' => self::faker()->numberBetween(2020, 2030),
        ];
    }
}
