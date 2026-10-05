<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use App\Service\LegacySessionService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Grants only while the admin session still belongs to the token's user. A JWT outlives
 * logout by up to its expiry, which on a shared desk computer would let the next person act
 * on participants' behalf in the name of whoever left.
 *
 * @extends Voter<string, mixed>
 */
class LegacySessionVoter extends Voter
{
    public const SAME_USER = 'GC_LEGACY_SESSION_SAME_USER';

    public function __construct(
        private readonly LegacySessionService $legacySession,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::SAME_USER;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (! $user instanceof User || $user->getId() === null) {
            return false;
        }

        return $this->legacySession->getCurrentUser()?->id() === $user->getId();
    }
}
