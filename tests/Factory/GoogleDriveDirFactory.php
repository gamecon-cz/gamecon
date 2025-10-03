<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\GoogleDriveDir;
use App\Repository\GoogleDriveDirRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<GoogleDriveDir>
 *
 * @method        GoogleDriveDir|Proxy create(array|callable $attributes = [])
 * @method static GoogleDriveDir|Proxy createOne(array $attributes = [])
 * @method static GoogleDriveDir|Proxy find(object|array|mixed $criteria)
 * @method static GoogleDriveDir|Proxy findOrCreate(array $attributes)
 * @method static GoogleDriveDir|Proxy first(string $sortedField = 'id')
 * @method static GoogleDriveDir|Proxy last(string $sortedField = 'id')
 * @method static GoogleDriveDir|Proxy random(array $attributes = [])
 * @method static GoogleDriveDir|Proxy randomOrCreate(array $attributes = [])
 * @method static GoogleDriveDirRepository|ProxyRepositoryDecorator repository()
 * @method static GoogleDriveDir[]|Proxy[] all()
 * @method static GoogleDriveDir[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static GoogleDriveDir[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static GoogleDriveDir[]|Proxy[] findBy(array $attributes)
 * @method static GoogleDriveDir[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static GoogleDriveDir[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class GoogleDriveDirFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return GoogleDriveDir::class;
    }

    protected function defaults(): array
    {
        return [
            'userId' => self::faker()->numberBetween(1, 1000),
            'dirId' => self::faker()->unique()->sha256(),
            'originalName' => self::faker()->word(),
            'tag' => self::faker()->word(),
        ];
    }
}
