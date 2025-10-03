<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\Badge;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Badge>
 */
final class BadgeFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Badge::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array|callable
    {
        return [
            'idUzivatele' => self::faker()->numberBetween(1, 10000),
            'oSobe'       => self::faker()->paragraph(3),
            'drd'         => self::faker()->paragraph(2),
        ];
    }

    protected function initialize(): static
    {
        return $this;
    }
}
