<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\Payment;
use App\Repository\PaymentRepository;
use App\Structure\Entity\PaymentEntityStructure;
use Zenstruck\Foundry\LazyValue;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<Payment>
 * @method        Payment|Proxy create(array|callable $attributes = [])
 * @method static Payment|Proxy createOne(array $attributes = [])
 * @method static Payment|Proxy find(object|array|mixed $criteria)
 * @method static Payment|Proxy findOrCreate(array $attributes)
 * @method static Payment|Proxy first(string $sortedField = 'id')
 * @method static Payment|Proxy last(string $sortedField = 'id')
 * @method static Payment|Proxy random(array $attributes = [])
 * @method static Payment|Proxy randomOrCreate(array $attributes = [])
 * @method static PaymentRepository|ProxyRepositoryDecorator repository()
 * @method static Payment[]|Proxy[] all()
 * @method static Payment[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static Payment[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static Payment[]|Proxy[] findBy(array $attributes)
 * @method static Payment[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static Payment[]|Proxy[] randomSet(int $number, array $attributes = [])
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
    protected function defaults(): array | callable
    {
        return [
            PaymentEntityStructure::fioId               => self::faker()->optional()->numberBetween(1000000000, 9999999999),
            PaymentEntityStructure::vs                  => self::faker()->optional()->numerify('##########'),
            PaymentEntityStructure::castka              => (string)self::faker()->randomFloat(2, 100, 5000),
            PaymentEntityStructure::rok                 => self::faker()->numberBetween(2020, 2030),
            PaymentEntityStructure::pripsanoNaUcetBanky => self::faker()->optional()->dateTimeBetween('-1 year', 'now'),
            PaymentEntityStructure::provedeno           => self::faker()->dateTimeBetween('-1 year', 'now'),
            PaymentEntityStructure::madeBy              => LazyValue::new(fn() => UserFactory::randomOrCreate()),
            PaymentEntityStructure::nazevProtiuctu      => self::faker()->optional()->company(),
            PaymentEntityStructure::cisloProtiuctu      => self::faker()->optional()->numerify('##########/####'),
            PaymentEntityStructure::kodBankyProtiuctu   => self::faker()->optional()->numerify('####'),
            PaymentEntityStructure::nazevBankyProtiuctu => self::faker()->optional()->words(2, true),
            PaymentEntityStructure::poznamka            => self::faker()->optional()->sentence(),
            PaymentEntityStructure::beneficiary         => LazyValue::new(fn() => UserFactory::randomOrCreate()),
            PaymentEntityStructure::skrytaPoznamka      => self::faker()->optional()->sentence(),
        ];
    }

    protected function initialize(): static
    {
        return $this;
    }
}
