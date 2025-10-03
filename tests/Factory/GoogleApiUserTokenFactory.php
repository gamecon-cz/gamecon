<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\GoogleApiUserToken;
use App\Repository\GoogleApiUserTokenRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<GoogleApiUserToken>
 *
 * @method        GoogleApiUserToken|Proxy create(array|callable $attributes = [])
 * @method static GoogleApiUserToken|Proxy createOne(array $attributes = [])
 * @method static GoogleApiUserToken|Proxy find(object|array|mixed $criteria)
 * @method static GoogleApiUserToken|Proxy findOrCreate(array $attributes)
 * @method static GoogleApiUserToken|Proxy first(string $sortedField = 'id')
 * @method static GoogleApiUserToken|Proxy last(string $sortedField = 'id')
 * @method static GoogleApiUserToken|Proxy random(array $attributes = [])
 * @method static GoogleApiUserToken|Proxy randomOrCreate(array $attributes = [])
 * @method static GoogleApiUserTokenRepository|ProxyRepositoryDecorator repository()
 * @method static GoogleApiUserToken[]|Proxy[] all()
 * @method static GoogleApiUserToken[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static GoogleApiUserToken[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static GoogleApiUserToken[]|Proxy[] findBy(array $attributes)
 * @method static GoogleApiUserToken[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static GoogleApiUserToken[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class GoogleApiUserTokenFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return GoogleApiUserToken::class;
    }

    protected function defaults(): array
    {
        return [
            'userId' => self::faker()->numberBetween(1, 1000),
            'googleClientId' => self::faker()->sha256(),
            'tokens' => self::faker()->text(),
        ];
    }
}
