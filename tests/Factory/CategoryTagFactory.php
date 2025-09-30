<?php

namespace Gamecon\Tests\Factory;

use App\Entity\CategoryTag;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<CategoryTag>
 */
final class CategoryTagFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return CategoryTag::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function defaults(): array | callable
    {
        return [
            'hlavniKategorie' => null,
            'nazev'           => self::faker()->text(128),
            'poradi'          => self::faker()->randomNumber(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this// ->afterInstantiate(function(CategoryTag $categoryTag): void {})
            ;
    }
}
