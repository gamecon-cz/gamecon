<?php

namespace Gamecon\Tests\Factory;

use App\Entity\ActivityStatus;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<ActivityStatus>
 */
final class ActivityStateFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ActivityStatus::class;
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
