<?php

namespace Gamecon\Tests\Factory;

use App\Entity\ActivityState;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<ActivityState>
 */
final class ActivityStateFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ActivityState::class;
    }

    protected function defaults(): array | callable
    {
        return [
            'nazev' => self::faker()->unique()->words(2, true),
        ];
    }

    protected function initialize(): static
    {
        return $this;
    }
}
