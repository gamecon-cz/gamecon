<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;

/**
 * Staff selling on someone's behalf, and which rules they may bypass doing it.
 *
 * The bypass is per guard rather than a single "admin mode" flag: the desk sells past the
 * deadline but must still respect stock, and the legacy sale path behaves exactly that way.
 */
final readonly class OperatorOverride
{
    public const GUARD_DEADLINE = 'deadline';

    public const SOURCE_KFC = 'kfc';

    /**
     * @param list<self::GUARD_*> $allowedGuards
     */
    private function __construct(
        public User $operator,
        public array $allowedGuards,
        public string $source,
    ) {
    }

    /**
     * The KFC till. Past the deadline is fine — after the merch cut-off every counter sale is
     * a bypass, so the log needs the source to stay readable. Stock is deliberately absent:
     * the legacy Shop::prodat() never consulted a date gate but always counted stock, and
     * nothing may oversell. Add a guard here only once CartService actually honours it.
     */
    public static function deskSale(User $operator): self
    {
        return new self($operator, [self::GUARD_DEADLINE], self::SOURCE_KFC);
    }

    public function allows(string $guard): bool
    {
        return in_array($guard, $this->allowedGuards, true);
    }
}
