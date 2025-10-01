<?php

namespace Gamecon\Tests\Factory;

use App\Entity\Accommodation;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Accommodation>
 */
final class AccommodationFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Accommodation::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function defaults(): array | callable
    {
        return [
            'uzivatel' => UserFactory::new(),
            'den'      => self::faker()->numberBetween(1, 7),
            'rok'      => self::faker()->numberBetween(2020, 2030),
            'pokoj'    => self::faker()->text(50),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this// ->afterInstantiate(function(Accommodation $accommodation): void {})
            ;
    }
}