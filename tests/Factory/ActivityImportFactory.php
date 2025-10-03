<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\ActivityImport;
use App\Repository\ActivityImportRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<ActivityImport>
 *
 * @method        ActivityImport|Proxy create(array|callable $attributes = [])
 * @method static ActivityImport|Proxy createOne(array $attributes = [])
 * @method static ActivityImport|Proxy find(object|array|mixed $criteria)
 * @method static ActivityImport|Proxy findOrCreate(array $attributes)
 * @method static ActivityImport|Proxy first(string $sortedField = 'id')
 * @method static ActivityImport|Proxy last(string $sortedField = 'id')
 * @method static ActivityImport|Proxy random(array $attributes = [])
 * @method static ActivityImport|Proxy randomOrCreate(array $attributes = [])
 * @method static ActivityImportRepository|ProxyRepositoryDecorator repository()
 * @method static ActivityImport[]|Proxy[] all()
 * @method static ActivityImport[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static ActivityImport[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static ActivityImport[]|Proxy[] findBy(array $attributes)
 * @method static ActivityImport[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static ActivityImport[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class ActivityImportFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ActivityImport::class;
    }

    protected function defaults(): array
    {
        return [
            'idUzivatele' => self::faker()->numberBetween(1, 1000),
            'googleSheetId' => self::faker()->sha1(),
            'cas' => self::faker()->dateTime(),
        ];
    }
}
