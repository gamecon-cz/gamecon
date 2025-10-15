<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\UserBadge;
use App\Repository\BadgeRepository;
use App\Structure\Entity\UserBadgeEntityStructure;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<UserBadge>
 *
 * @method        UserBadge|Proxy create(array|callable $attributes = [])
 * @method static UserBadge|Proxy createOne(array $attributes = [])
 * @method static UserBadge|Proxy find(object|array|mixed $criteria)
 * @method static UserBadge|Proxy findOrCreate(array $attributes)
 * @method static UserBadge|Proxy first(string $sortedField = 'id')
 * @method static UserBadge|Proxy last(string $sortedField = 'id')
 * @method static UserBadge|Proxy random(array $attributes = [])
 * @method static UserBadge|Proxy randomOrCreate(array $attributes = [])
 * @method static BadgeRepository|ProxyRepositoryDecorator repository()
 * @method static UserBadge[]|Proxy[] all()
 * @method static UserBadge[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static UserBadge[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static UserBadge[]|Proxy[] findBy(array $attributes)
 * @method static UserBadge[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static UserBadge[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class UserBadgeFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return UserBadge::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array | callable
    {
        return [
            UserBadgeEntityStructure::oSobe => self::faker()->paragraph(3),
            UserBadgeEntityStructure::drd   => self::faker()->paragraph(2),
        ];
    }

    protected function initialize(): static
    {
        return $this;
    }
}
