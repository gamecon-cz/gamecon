<?php

namespace Gamecon\Tests\Factory;

use App\Entity\Page;
use App\Repository\PageRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<Page>
 * @method        Page|Proxy create(array|callable $attributes = [])
 * @method static Page|Proxy createOne(array $attributes = [])
 * @method static Page|Proxy find(object|array|mixed $criteria)
 * @method static Page|Proxy findOrCreate(array $attributes)
 * @method static Page|Proxy first(string $sortedField = 'id')
 * @method static Page|Proxy last(string $sortedField = 'id')
 * @method static Page|Proxy random(array $attributes = [])
 * @method static Page|Proxy randomOrCreate(array $attributes = [])
 * @method static PageRepository|ProxyRepositoryDecorator repository()
 * @method static Page[]|Proxy[] all()
 * @method static Page[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static Page[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static Page[]|Proxy[] findBy(array $attributes)
 * @method static Page[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static Page[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class PageFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Page::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function defaults(): array | callable
    {
        return [
            'obsah'      => self::faker()->text(),
            'poradi'     => self::faker()->numberBetween(1, 32767),
            'urlStranky' => self::faker()->text(64),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this// ->afterInstantiate(function(Page $page): void {})
            ;
    }
}
