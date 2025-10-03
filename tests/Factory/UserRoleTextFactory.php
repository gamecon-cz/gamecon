<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\UserRoleText;
use App\Repository\UserRoleTextRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<UserRoleText>
 *
 * @method        UserRoleText|Proxy create(array|callable $attributes = [])
 * @method static UserRoleText|Proxy createOne(array $attributes = [])
 * @method static UserRoleText|Proxy find(object|array|mixed $criteria)
 * @method static UserRoleText|Proxy findOrCreate(array $attributes)
 * @method static UserRoleText|Proxy first(string $sortedField = 'id')
 * @method static UserRoleText|Proxy last(string $sortedField = 'id')
 * @method static UserRoleText|Proxy random(array $attributes = [])
 * @method static UserRoleText|Proxy randomOrCreate(array $attributes = [])
 * @method static UserRoleTextRepository|ProxyRepositoryDecorator repository()
 * @method static UserRoleText[]|Proxy[] all()
 * @method static UserRoleText[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static UserRoleText[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static UserRoleText[]|Proxy[] findBy(array $attributes)
 * @method static UserRoleText[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static UserRoleText[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class UserRoleTextFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return UserRoleText::class;
    }

    protected function defaults(): array
    {
        return [
            'vyznamRole' => self::faker()->word(),
            'idUzivatele' => self::faker()->numberBetween(1, 1000),
            'popisRole' => self::faker()->text(),
        ];
    }
}
