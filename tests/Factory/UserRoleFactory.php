<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\UserRole;
use App\Repository\UserRoleRepository;
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
            'idUzivatele' => self::faker()->numberBetween(1, 1000),
            'idRole' => self::faker()->numberBetween(1, 100),
            'posazen' => self::faker()->dateTime(),
            'posadil' => self::faker()->numberBetween(1, 1000),
        ];
    }
}
