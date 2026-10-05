<?php

declare(strict_types=1);

namespace App\Tests\Security\Voter;

use App\Entity\User;
use App\Security\Voter\LegacySessionVoter;
use App\Service\LegacySessionService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class LegacySessionVoterTest extends TestCase
{
    private function vote(?int $sessionUserId, int $tokenUserId = 7, string $attribute = LegacySessionVoter::SAME_USER): int
    {
        $sessionUser = null;
        if ($sessionUserId !== null) {
            $sessionUser = $this->createMock(\Uzivatel::class);
            $sessionUser->method('id')->willReturn($sessionUserId);
        }
        $legacySession = $this->createMock(LegacySessionService::class);
        $legacySession->method('getCurrentUser')->willReturn($sessionUser);

        $tokenUser = $this->createMock(User::class);
        $tokenUser->method('getId')->willReturn($tokenUserId);
        $tokenUser->method('getRoles')->willReturn(['ROLE_USER']);

        return (new LegacySessionVoter($legacySession))->vote(
            new UsernamePasswordToken($tokenUser, 'api', ['ROLE_USER']),
            null,
            [$attribute],
        );
    }

    public function testSessionOfTheTokensUserIsGranted(): void
    {
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote(sessionUserId: 7));
    }

    public function testEndedSessionIsRefused(): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote(sessionUserId: null));
    }

    public function testSessionOfSomeoneElseIsRefused(): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote(sessionUserId: 8));
    }

    public function testOtherAttributesAreLeftToOtherVoters(): void
    {
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote(sessionUserId: 7, attribute: 'ROLE_ADMIN'));
    }
}
