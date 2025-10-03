<?php declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\Text;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Text>
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
    protected function defaults(): array|callable
    {
        return [
            'id'   => self::faker()->numberBetween(1000000, 9999999), // hash-based ID
            'text' => self::faker()->text(500),
        ];
    }

    protected function initialize(): static
    {
        return $this;
    }
}