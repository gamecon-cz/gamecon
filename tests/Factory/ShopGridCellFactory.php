<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\ShopGridCell;
use App\Repository\ShopGridCellRepository;
use App\Structure\Entity\ShopGridCellEntityStructure;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<ShopGridCell>
 *
 * @method        ShopGridCell|Proxy create(array|callable $attributes = [])
 * @method static ShopGridCell|Proxy createOne(array $attributes = [])
 * @method static ShopGridCell|Proxy find(object|array|mixed $criteria)
 * @method static ShopGridCell|Proxy findOrCreate(array $attributes)
 * @method static ShopGridCell|Proxy first(string $sortedField = 'id')
 * @method static ShopGridCell|Proxy last(string $sortedField = 'id')
 * @method static ShopGridCell|Proxy random(array $attributes = [])
 * @method static ShopGridCell|Proxy randomOrCreate(array $attributes = [])
 * @method static ShopGridCellRepository|ProxyRepositoryDecorator repository()
 * @method static ShopGridCell[]|Proxy[] all()
 * @method static ShopGridCell[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static ShopGridCell[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static ShopGridCell[]|Proxy[] findBy(array $attributes)
 * @method static ShopGridCell[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static ShopGridCell[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class ShopGridCellFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ShopGridCell::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array|callable
    {
        return [
            ShopGridCellEntityStructure::typ       => self::faker()->numberBetween(ShopGridCell::TYPE_ITEM, ShopGridCell::TYPE_SUMMARY),
            ShopGridCellEntityStructure::text      => self::faker()->optional()->words(3, true),
            ShopGridCellEntityStructure::barva     => self::faker()->optional()->hexColor(),
            ShopGridCellEntityStructure::barvaText => self::faker()->optional()->hexColor(),
            ShopGridCellEntityStructure::cilId     => self::faker()->optional()->numberBetween(1, 100),
            ShopGridCellEntityStructure::shopGrid  => null,
        ];
    }

    protected function initialize(): static
    {
        return $this;
    }
}
