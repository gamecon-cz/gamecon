<?php

namespace Gamecon\Tests\Factory;

use App\Entity\Page;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Page>
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
