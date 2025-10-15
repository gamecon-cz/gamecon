<?php
declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\Text;
use App\Repository\TextRepository;
use App\Structure\Entity\TextEntityStructure;
use Zenstruck\Foundry\LazyValue;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<Text>
 *
 * @method        Text|Proxy create(array|callable $attributes = [])
 * @method static Text|Proxy createOne(array $attributes = [])
 * @method static Text|Proxy find(object|array|mixed $criteria)
 * @method static Text|Proxy findOrCreate(array $attributes)
 * @method static Text|Proxy first(string $sortedField = 'id')
 * @method static Text|Proxy last(string $sortedField = 'id')
 * @method static Text|Proxy random(array $attributes = [])
 * @method static Text|Proxy randomOrCreate(array $attributes = [])
 * @method static TextRepository|ProxyRepositoryDecorator repository()
 * @method static Text[]|Proxy[] all()
 * @method static Text[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static Text[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static Text[]|Proxy[] findBy(array $attributes)
 * @method static Text[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static Text[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class TextFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Text::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array | callable
    {
        return [
            'text' => self::faker()->unique()->text(450),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this->beforeInstantiate(
            function (
                array       $parameters,
            ): array {
                $parameters[TextEntityStructure::id] = dbTextHash($parameters[TextEntityStructure::text], false);

                return $parameters;
            },
        );
    }
}
