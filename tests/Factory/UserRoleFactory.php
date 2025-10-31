<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\UserRole;
use App\Repository\UserRoleRepository;
use App\Structure\Entity\UserEntityStructure;
use App\Structure\Entity\UserRoleEntityStructure;
use Zenstruck\Foundry\LazyValue;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<UserRole>
 *
 * @method        UserRole|Proxy create(array|callable $attributes = [])
 * @method static UserRole|Proxy createOne(array $attributes = [])
 * @method static UserRole|Proxy find(object|array|mixed $criteria)
 * @method static UserRole|Proxy findOrCreate(array $attributes)
 * @method static UserRole|Proxy first(string $sortedField = 'id')
 * @method static UserRole|Proxy last(string $sortedField = 'id')
 * @method static UserRole|Proxy random(array $attributes = [])
 * @method static UserRole|Proxy randomOrCreate(array $attributes = [])
 * @method static UserRoleRepository|ProxyRepositoryDecorator repository()
 * @method static UserRole[]|Proxy[] all()
 * @method static UserRole[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static UserRole[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static UserRole[]|Proxy[] findBy(array $attributes)
 * @method static UserRole[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static UserRole[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class UserRoleFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return UserRole::class;
    }

    protected function defaults(): array
    {
        return [
            UserRoleEntityStructure::user => LazyValue::new(fn() => UserFactory::createOne()),
            UserRoleEntityStructure::role => LazyValue::new(fn() => RoleFactory::createOne()),
            UserRoleEntityStructure::posazen => self::faker()->dateTime(),
            UserRoleEntityStructure::givenBy => LazyValue::new(fn() => UserFactory::find([
                UserEntityStructure::id => \Uzivatel::SYSTEM
            ])),
        ];
    }
}
