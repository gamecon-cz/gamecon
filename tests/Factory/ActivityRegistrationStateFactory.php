<?php

namespace Gamecon\Tests\Factory;

use App\Entity\ActivityRegistrationState;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<ActivityRegistrationState>
 */
final class ActivityRegistrationStateFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ActivityRegistrationState::class;
    }

    protected function defaults(): array | callable
    {
        return [
            'id'            => self::faker()->unique()->numberBetween(1, 1000),
            'nazev'         => self::faker()->unique()->words(3, true),
            'platbaProcent' => self::faker()->randomFloat(2, 0, 100),
        ];
    }

    protected function initialize(): static
    {
        return $this;
    }
}
