<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\ShopGridCell;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<ShopGridCell>
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
            'typ'       => self::faker()->numberBetween(ShopGridCell::TYPE_ITEM, ShopGridCell::TYPE_SUMMARY),
            'text'      => self::faker()->optional()->words(3, true),
            'barva'     => self::faker()->optional()->hexColor(),
            'barvaText' => self::faker()->optional()->hexColor(),
            'cilId'     => self::faker()->optional()->numberBetween(1, 100),
            'mrizkaId'  => self::faker()->optional()->numberBetween(1, 50),
        ];
    }

    protected function initialize(): static
    {
        return $this;
    }
}
