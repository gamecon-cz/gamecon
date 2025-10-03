<?php

namespace Gamecon\Tests\Factory;

use App\Entity\News;
use Zenstruck\Foundry\LazyValue;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<News>
 */
final class NewsFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return News::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function defaults(): array | callable
    {
        return [
            'typ'   => News::TYPE_NEWS,
            'vydat' => new \DateTime('2024-01-15 10:00:00'),
            'url'   => 'test-news-' . uniqid(),
            'nazev' => 'Test novinka ' . uniqid(),
            'autor' => 'Test Autor ' . self::faker()->name(),
            'text'  => LazyValue::new(fn() => TextFactory::createOne()),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this;
    }
}
