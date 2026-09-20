<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\Product;
use App\Enum\ProductStateEnum;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Product>
 */
final class ProductFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Product::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function defaults(): array|callable
    {
        return [
            'name'             => 'Test Předmět ' . uniqid(),
            'code'             => 'TEST_' . strtoupper(uniqid()),
            'currentPrice'     => (string) self::faker()->randomFloat(2, 0, 9999),
            'state'            => ProductStateEnum::from(self::faker()->numberBetween(0, 3)),
            'availableUntil'   => null,
            'producedQuantity' => self::faker()->numberBetween(0, 1000),
            'accommodationDay' => null,
            'description'      => self::faker()->text(200),
            'archivedAt'       => null,
        ];
    }
}
