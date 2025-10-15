<?php

namespace Gamecon\Tests\Factory;

use App\Entity\CategoryTag;
use App\Repository\CategoryTagRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;
use App\Structure\Entity\CategoryTagEntityStructure;

/**
 * @extends PersistentProxyObjectFactory<CategoryTag>
 *
 * @method        CategoryTag|Proxy create(array|callable $attributes = [])
 * @method static CategoryTag|Proxy createOne(array $attributes = [])
 * @method static CategoryTag|Proxy find(object|array|mixed $criteria)
 * @method static CategoryTag|Proxy findOrCreate(array $attributes)
 * @method static CategoryTag|Proxy first(string $sortedField = 'id')
 * @method static CategoryTag|Proxy last(string $sortedField = 'id')
 * @method static CategoryTag|Proxy random(array $attributes = [])
 * @method static CategoryTag|Proxy randomOrCreate(array $attributes = [])
 * @method static CategoryTagRepository|ProxyRepositoryDecorator repository()
 * @method static CategoryTag[]|Proxy[] all()
 * @method static CategoryTag[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static CategoryTag[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static CategoryTag[]|Proxy[] findBy(array $attributes)
 * @method static CategoryTag[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static CategoryTag[]|Proxy[] randomSet(int $number, array $attributes = [])
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
            CategoryTagEntityStructure::mainCategoryTag => null,
            CategoryTagEntityStructure::nazev           => self::faker()->unique()->text(128),
            CategoryTagEntityStructure::poradi          => self::faker()->randomNumber(),
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
