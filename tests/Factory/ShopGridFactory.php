<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\ShopGrid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<ShopGrid>
 */
final class ShopGridFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ShopGrid::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array|callable
    {
        return [
            'text' => self::faker()->optional()->words(3, true),
        ];
    }

    protected function initialize(): static
    {
        return $this;
    }
}
