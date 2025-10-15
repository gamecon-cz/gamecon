<?php

namespace Gamecon\Tests\Factory;

use App\Entity\NewsletterSubscription;
use App\Repository\NewsletterSubscriptionRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<NewsletterSubscription>
 *
 * @method        NewsletterSubscription|Proxy create(array|callable $attributes = [])
 * @method static NewsletterSubscription|Proxy createOne(array $attributes = [])
 * @method static NewsletterSubscription|Proxy find(object|array|mixed $criteria)
 * @method static NewsletterSubscription|Proxy findOrCreate(array $attributes)
 * @method static NewsletterSubscription|Proxy first(string $sortedField = 'id')
 * @method static NewsletterSubscription|Proxy last(string $sortedField = 'id')
 * @method static NewsletterSubscription|Proxy random(array $attributes = [])
 * @method static NewsletterSubscription|Proxy randomOrCreate(array $attributes = [])
 * @method static NewsletterSubscriptionRepository|ProxyRepositoryDecorator repository()
 * @method static NewsletterSubscription[]|Proxy[] all()
 * @method static NewsletterSubscription[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static NewsletterSubscription[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static NewsletterSubscription[]|Proxy[] findBy(array $attributes)
 * @method static NewsletterSubscription[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static NewsletterSubscription[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class NewsletterSubscriptionFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return NewsletterSubscription::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function defaults(): array | callable
    {
        return [
            'email' => self::faker()->unique()->email(),
            'kdy'   => self::faker()->dateTime(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this// ->afterInstantiate(function(NewsletterSubscription $newsletterSubscription): void {})
            ;
    }
}
