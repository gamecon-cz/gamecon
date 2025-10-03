<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\UserUrl;
use App\Repository\UserUrlRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<UserUrl>
 *
 * @method        UserUrl|Proxy create(array|callable $attributes = [])
 * @method static UserUrl|Proxy createOne(array $attributes = [])
 * @method static UserUrl|Proxy find(object|array|mixed $criteria)
 * @method static UserUrl|Proxy findOrCreate(array $attributes)
 * @method static UserUrl|Proxy first(string $sortedField = 'id')
 * @method static UserUrl|Proxy last(string $sortedField = 'id')
 * @method static UserUrl|Proxy random(array $attributes = [])
 * @method static UserUrl|Proxy randomOrCreate(array $attributes = [])
 * @method static UserUrlRepository|ProxyRepositoryDecorator repository()
 * @method static UserUrl[]|Proxy[] all()
 * @method static UserUrl[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static UserUrl[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static UserUrl[]|Proxy[] findBy(array $attributes)
 * @method static UserUrl[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static UserUrl[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class UserUrlFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return UserUrl::class;
    }

    protected function defaults(): array
    {
        return [
            'idUzivatele' => self::faker()->numberBetween(1, 1000),
            'url' => self::faker()->unique()->slug(),
        ];
    }
}
