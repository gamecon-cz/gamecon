<?php

declare(strict_types=1);

namespace App\Service;

use App\Discount\DiscountRuleLoader;
use App\Entity\User;

/**
 * Answers what legacy Uzivatel::maPravo() does, without the legacy user. Read on every call
 * rather than walked from the user's roles: a role change in this request must count, and a
 * collection loaded before it would not see it.
 */
class UserPermissions
{
    public function __construct(
        private readonly DiscountRuleLoader $ruleLoader,
    ) {
    }

    /**
     * @param int $permission a Gamecon\Pravo constant
     */
    public function has(User $user, int $permission, int $year): bool
    {
        $userId = $user->getId();

        return $userId !== null && in_array($permission, $this->ruleLoader->rightsOfUser($userId, $year), true);
    }
}
