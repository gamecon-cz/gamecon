<?php

declare(strict_types=1);

namespace App\Tests\Security\Voter;

use App\Entity\User;
use App\Enum\PermissionEnum;
use App\Security\Voter\PermissionVoter;
use App\Service\CurrentYearProviderInterface;
use App\Service\UserPermissions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class PermissionVoterTest extends TestCase
{
    private const ROK = 2026;

    /**
     * @param list<PermissionEnum> $held
     */
    private function voter(array $held): PermissionVoter
    {
        $userPermissions = $this->createMock(UserPermissions::class);
        $userPermissions->method('has')->willReturnCallback(
            static fn (User $user, int $permission, int $year): bool => $year === self::ROK
                && in_array($permission, array_map(static fn (PermissionEnum $case): int => $case->value, $held), true),
        );
        $yearProvider = $this->createMock(CurrentYearProviderInterface::class);
        $yearProvider->method('getCurrentYear')->willReturn(self::ROK);

        return new PermissionVoter($userPermissions, $yearProvider);
    }

    /**
     * @param list<PermissionEnum> $required
     */
    private function vote(PermissionVoter $voter, array $required, string $attribute = PermissionVoter::ANY_OF): int
    {
        $user = $this->createMock(User::class);
        $user->method('getRoles')->willReturn(['ROLE_USER']);

        return $voter->vote(
            new UsernamePasswordToken($user, 'api', ['ROLE_USER']),
            array_map(static fn (PermissionEnum $case): int => $case->value, $required),
            [$attribute],
        );
    }

    public function testOneOfTheListedRightsIsEnough(): void
    {
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote(
            $this->voter([PermissionEnum::ADMINISTRACE_INFOPULT]),
            [PermissionEnum::ADMINISTRACE_UBYTOVANI, PermissionEnum::ADMINISTRACE_INFOPULT],
        ));
    }

    public function testNoneOfTheListedRightsIsRefused(): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote(
            $this->voter([PermissionEnum::ADMINISTRACE_INFOPULT]),
            [PermissionEnum::ADMINISTRACE_UBYTOVANI],
        ));
    }

    public function testSignedOutCallerIsRefused(): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter([PermissionEnum::ADMINISTRACE_INFOPULT])->vote(
            new NullToken(),
            [PermissionEnum::ADMINISTRACE_INFOPULT->value],
            [PermissionVoter::ANY_OF],
        ));
    }

    public function testOtherAttributesAreLeftToOtherVoters(): void
    {
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote(
            $this->voter([PermissionEnum::ADMINISTRACE_INFOPULT]),
            [PermissionEnum::ADMINISTRACE_INFOPULT],
            'ROLE_ADMIN',
        ));
    }

    /**
     * An empty list would refuse everyone, which reads like a working gate until someone with
     * the right is turned away.
     */
    public function testAnEmptyListIsAMistakeNotARefusal(): void
    {
        $this->expectException(\LogicException::class);

        $this->vote($this->voter([PermissionEnum::ADMINISTRACE_INFOPULT]), []);
    }
}
