<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\NewsletterSubscriptionLog;
use App\Repository\NewsletterSubscriptionLogRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<NewsletterSubscriptionLog>
 *
 * @method        NewsletterSubscriptionLog|Proxy create(array|callable $attributes = [])
 * @method static NewsletterSubscriptionLog|Proxy createOne(array $attributes = [])
 * @method static NewsletterSubscriptionLog|Proxy find(object|array|mixed $criteria)
 * @method static NewsletterSubscriptionLog|Proxy findOrCreate(array $attributes)
 * @method static NewsletterSubscriptionLog|Proxy first(string $sortedField = 'id')
 * @method static NewsletterSubscriptionLog|Proxy last(string $sortedField = 'id')
 * @method static NewsletterSubscriptionLog|Proxy random(array $attributes = [])
 * @method static NewsletterSubscriptionLog|Proxy randomOrCreate(array $attributes = [])
 * @method static NewsletterSubscriptionLogRepository|ProxyRepositoryDecorator repository()
 * @method static NewsletterSubscriptionLog[]|Proxy[] all()
 * @method static NewsletterSubscriptionLog[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static NewsletterSubscriptionLog[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static NewsletterSubscriptionLog[]|Proxy[] findBy(array $attributes)
 * @method static NewsletterSubscriptionLog[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static NewsletterSubscriptionLog[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class NewsletterSubscriptionLogFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return NewsletterSubscriptionLog::class;
    }

    protected function defaults(): array
    {
        return [
            'email' => self::faker()->email(),
            'kdy' => self::faker()->dateTime(),
            'stav' => self::faker()->word(),
        ];
    }
}
