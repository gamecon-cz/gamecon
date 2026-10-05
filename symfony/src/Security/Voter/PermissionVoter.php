<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use App\Enum\PermissionEnum;
use App\Service\CurrentYearProviderInterface;
use App\Service\UserPermissions;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Grants when the signed-in user holds at least one of the listed rights this year:
 * `is_granted(PermissionVoter::ANY_OF, [PermissionEnum::X->value, ...])`.
 *
 * Rights rather than roles, because the roles carrying a right are per-year (`gc2026_infopult`),
 * so no role name stays true from one year to the next.
 *
 * @extends Voter<string, mixed>
 */
class PermissionVoter extends Voter
{
    public const ANY_OF = 'GC_PERMISSION_ANY_OF';

    public function __construct(
        private readonly UserPermissions $userPermissions,
        private readonly CurrentYearProviderInterface $currentYearProvider,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::ANY_OF;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        // An empty or malformed list is a mistake in the operation, not a refusal to report.
        if (! is_array($subject) || $subject === []) {
            throw new \LogicException(self::ANY_OF . ' needs a non-empty list of PermissionEnum values.');
        }

        $user = $token->getUser();
        if (! $user instanceof User) {
            return false;
        }

        $year = $this->currentYearProvider->getCurrentYear();
        foreach ($subject as $permission) {
            if ($this->userPermissions->has($user, PermissionEnum::from($permission)->value, $year)) {
                return true;
            }
        }

        return false;
    }
}
