<?php

namespace Gamecon\Tests\Factory;

use App\Entity\ActivityType;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<ActivityType>
 */
final class ActivityTypeFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ActivityType::class;
    }

    protected function defaults(): array | callable
    {
        return [
            'id'            => self::faker()->unique()->numberBetween(1, 1000),
            'typ1p'         => self::faker()->words(2, true),
            'typ1pmn'       => self::faker()->words(2, true),
            'urlTypuMn'     => self::faker()->slug(),
            'strankaO'      => self::faker()->numberBetween(1, 100),
            'poradi'        => self::faker()->numberBetween(1, 10),
            'mailNeucast'   => self::faker()->boolean(20), // 20% chance
            'popisKratky'   => self::faker()->sentence(),
            'aktivni'       => true,
            'zobrazitVMenu' => true,
            'kodTypu'       => self::faker()->optional()->word(),
        ];
    }

    protected function initialize(): static
    {
        return $this;
    }
}
