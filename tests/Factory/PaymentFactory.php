<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\Payment;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Payment>
 */
final class PaymentFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Payment::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array|callable
    {
        return [
            'idUzivatele'          => null, // FK can be null
            'fioId'                => self::faker()->optional()->numberBetween(1000000000, 9999999999),
            'vs'                   => self::faker()->optional()->numerify('##########'),
            'castka'               => (string) self::faker()->randomFloat(2, 100, 5000),
            'rok'                  => self::faker()->numberBetween(2020, 2030),
            'pripsanoNaUcetBanky'  => self::faker()->optional()->dateTimeBetween('-1 year', 'now'),
            'provedeno'            => self::faker()->dateTimeBetween('-1 year', 'now'),
            'provedl'              => self::faker()->numberBetween(1, 1000),
            'nazevProtiuctu'       => self::faker()->optional()->company(),
            'cisloProtiuctu'       => self::faker()->optional()->numerify('##########/####'),
            'kodBankyProtiuctu'    => self::faker()->optional()->numerify('####'),
            'nazevBankyProtiuctu'  => self::faker()->optional()->words(2, true),
            'poznamka'             => self::faker()->optional()->sentence(),
            'skrytaPoznamka'       => self::faker()->optional()->sentence(),
        ];
    }

    protected function initialize(): static
    {
        return $this;
    }
}
