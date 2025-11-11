<?php

namespace Gamecon\Tests\Factory;

use App\Entity\News;
use App\Repository\NewsRepository;
use Zenstruck\Foundry\LazyValue;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<News>
 *
 * @method        News|Proxy create(array|callable $attributes = [])
 * @method static News|Proxy createOne(array $attributes = [])
 * @method static News|Proxy find(object|array|mixed $criteria)
 * @method static News|Proxy findOrCreate(array $attributes)
 * @method static News|Proxy first(string $sortedField = 'id')
 * @method static News|Proxy last(string $sortedField = 'id')
 * @method static News|Proxy random(array $attributes = [])
 * @method static News|Proxy randomOrCreate(array $attributes = [])
 * @method static NewsRepository|ProxyRepositoryDecorator repository()
 * @method static News[]|Proxy[] all()
 * @method static News[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static News[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static News[]|Proxy[] findBy(array $attributes)
 * @method static News[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static News[]|Proxy[] randomSet(int $number, array $attributes = [])
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
            'text'  => self::faker()->unique()->text(450),
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
