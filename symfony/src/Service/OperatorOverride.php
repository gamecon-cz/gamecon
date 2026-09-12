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

    public const GUARD_ORGANIZER_STOCK = 'organizer_stock';

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
     * The KFC till. Past the deadline is fine, and so is stock set aside for organizers —
     * whoever stands at the counter decides who gets it. Total stock is NOT bypassable:
     * nothing may sell a piece that does not exist.
     */
    public static function deskSale(User $operator): self
    {
        return new self($operator, [self::GUARD_DEADLINE, self::GUARD_ORGANIZER_STOCK], self::SOURCE_KFC);
    }

    public function allows(string $guard): bool
    {
        return in_array($guard, $this->allowedGuards, true);
    }
}
