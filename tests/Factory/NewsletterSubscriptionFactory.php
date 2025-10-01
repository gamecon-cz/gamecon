<?php

namespace Gamecon\Tests\Factory;

use App\Entity\NewsletterSubscription;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<NewsletterSubscription>
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