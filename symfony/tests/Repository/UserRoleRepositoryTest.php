<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\User;
use App\Enum\RoleMeaning;
use App\Enum\RoleTypeEnum;
use App\Repository\UserRoleRepository;
use App\Tests\AbstractDatabaseKernelTestCase;
use Gamecon\Role\Role;

/**
 * holdsAnyMeaning() is the Doctrine-side copy of what the platne_role_uzivatelu view lets
 * through, so each way a role stays valid, and the ways it does not, is pinned here.
 */
class UserRoleRepositoryTest extends AbstractDatabaseKernelTestCase
{
    private const int ROLE_OF_NO_YEAR = -990201;
    private const int PARTICIPATION_ROLE_OF_PAST_YEAR = -990202;
    private const int ROLE_OF_LATER_YEAR = -990203;
    private const int ROLE_OF_PAST_YEAR = -990204;
    private const int NARRATOR_OF_THIS_YEAR = -990205;
    private const int HERMAN_OF_THIS_YEAR = -990206;
    private const int HERMAN_OF_NO_YEAR = -990207;
    private const int BACKSTAGE_OF_PAST_YEAR = -990208;
    private const int VOLUNTEER_OF_LATER_YEAR = -990209;

    public function testRoleOfNoYearCounts(): void
    {
        $this->role(self::ROLE_OF_NO_YEAR, Role::JAKYKOLI_ROCNIK, RoleTypeEnum::PERMANENT, RoleMeaning::MINI_ORG);

        self::assertTrue($this->holds(self::ROLE_OF_NO_YEAR, RoleMeaning::MINI_ORG));
    }

    public function testParticipationRoleOfAnyYearCounts(): void
    {
        $this->role(self::PARTICIPATION_ROLE_OF_PAST_YEAR, ROCNIK - 1, RoleTypeEnum::PARTICIPATION, RoleMeaning::PRIHLASEN);

        self::assertTrue($this->holds(self::PARTICIPATION_ROLE_OF_PAST_YEAR, RoleMeaning::PRIHLASEN));
    }

    public function testRoleOfPastYearDoesNotCount(): void
    {
        $this->role(self::ROLE_OF_PAST_YEAR, ROCNIK - 1, RoleTypeEnum::YEARLY, RoleMeaning::VYPRAVEC);

        self::assertFalse($this->holds(self::ROLE_OF_PAST_YEAR, RoleMeaning::VYPRAVEC));
    }

    public function testRoleOfLaterYearDoesNotCount(): void
    {
        $this->role(self::ROLE_OF_LATER_YEAR, ROCNIK + 1, RoleTypeEnum::YEARLY, RoleMeaning::VYPRAVEC);

        self::assertFalse($this->holds(self::ROLE_OF_LATER_YEAR, RoleMeaning::VYPRAVEC));
    }

    public function testOtherMeaningDoesNotCount(): void
    {
        $this->role(self::ROLE_OF_NO_YEAR, Role::JAKYKOLI_ROCNIK, RoleTypeEnum::PERMANENT, RoleMeaning::MINI_ORG);

        self::assertFalse($this->holds(self::ROLE_OF_NO_YEAR, RoleMeaning::CFO));
    }

    public function testValidMeaningsAreOnlyThoseOfRolesValidThisYear(): void
    {
        $this->role(self::NARRATOR_OF_THIS_YEAR, ROCNIK, RoleTypeEnum::YEARLY, RoleMeaning::VYPRAVEC);
        $this->role(self::ROLE_OF_NO_YEAR, Role::JAKYKOLI_ROCNIK, RoleTypeEnum::PERMANENT, RoleMeaning::MINI_ORG);
        $this->role(self::PARTICIPATION_ROLE_OF_PAST_YEAR, ROCNIK - 1, RoleTypeEnum::PARTICIPATION, RoleMeaning::PRIHLASEN);
        $this->role(self::BACKSTAGE_OF_PAST_YEAR, ROCNIK - 1, RoleTypeEnum::YEARLY, RoleMeaning::ZAZEMI);
        $this->role(self::VOLUNTEER_OF_LATER_YEAR, ROCNIK + 1, RoleTypeEnum::YEARLY, RoleMeaning::BRIGADNIK);
        $user = $this->userWithRoles([
            self::NARRATOR_OF_THIS_YEAR,
            self::ROLE_OF_NO_YEAR,
            self::PARTICIPATION_ROLE_OF_PAST_YEAR,
            self::BACKSTAGE_OF_PAST_YEAR,
            self::VOLUNTEER_OF_LATER_YEAR,
        ]);

        $meanings = static::getContainer()->get(UserRoleRepository::class)->validMeanings($user, ROCNIK);

        self::assertEqualsCanonicalizing(
            [RoleMeaning::VYPRAVEC, RoleMeaning::MINI_ORG, RoleMeaning::PRIHLASEN],
            $meanings,
        );
    }

    public function testValidMeaningIsListedOnceWhenTwoRolesCarryIt(): void
    {
        $this->role(self::HERMAN_OF_THIS_YEAR, ROCNIK, RoleTypeEnum::YEARLY, RoleMeaning::HERMAN);
        $this->role(self::HERMAN_OF_NO_YEAR, Role::JAKYKOLI_ROCNIK, RoleTypeEnum::PERMANENT, RoleMeaning::HERMAN);
        $user = $this->userWithRoles([self::HERMAN_OF_THIS_YEAR, self::HERMAN_OF_NO_YEAR]);

        $meanings = static::getContainer()->get(UserRoleRepository::class)->validMeanings($user, ROCNIK);

        self::assertSame([RoleMeaning::HERMAN], $meanings);
    }

    public function testNoRolesGiveNoMeanings(): void
    {
        $user = $this->userWithRoles([]);

        self::assertSame([], static::getContainer()->get(UserRoleRepository::class)->validMeanings($user, ROCNIK));
    }

    public function testOrganizerIsOnlyWhoHoldsAnOrganizerRoleValidThisYear(): void
    {
        $this->role(self::NARRATOR_OF_THIS_YEAR, ROCNIK, RoleTypeEnum::YEARLY, RoleMeaning::VYPRAVEC);
        $this->role(self::ROLE_OF_PAST_YEAR, ROCNIK - 1, RoleTypeEnum::YEARLY, RoleMeaning::VYPRAVEC);
        $this->role(self::HERMAN_OF_THIS_YEAR, ROCNIK, RoleTypeEnum::YEARLY, RoleMeaning::HERMAN);
        $repository = static::getContainer()->get(UserRoleRepository::class);

        self::assertTrue($repository->isOrganizer($this->userWithRoles([self::NARRATOR_OF_THIS_YEAR]), ROCNIK), 'narrator this year');
        self::assertFalse($repository->isOrganizer($this->userWithRoles([self::ROLE_OF_PAST_YEAR]), ROCNIK), 'narrator only in a past year');
        self::assertFalse($repository->isOrganizer($this->userWithRoles([self::HERMAN_OF_THIS_YEAR]), ROCNIK), 'a role outside the organizer list');
        self::assertFalse($repository->isOrganizer($this->userWithRoles([]), ROCNIK), 'no role');
    }

    private function role(int $roleId, int $year, RoleTypeEnum $type, RoleMeaning $meaning): void
    {
        $this->connection()->executeStatement(
            "INSERT INTO role_seznam (id_role, kod_role, nazev_role, popis_role, rocnik_role, typ_role, vyznam_role)
             VALUES (:role, :code, :code, '', :year, :type, :meaning)",
            [
                'role'    => $roleId,
                'code'    => 'TEST_REPO_' . abs($roleId),
                'year'    => $year,
                'type'    => $type->value,
                'meaning' => $meaning->value,
            ],
        );
    }

    /**
     * Gives a new user the role and asks whether they hold the meaning this year.
     */
    private function holds(int $roleId, RoleMeaning $meaning): bool
    {
        return static::getContainer()->get(UserRoleRepository::class)
            ->holdsAnyMeaning($this->userWithRoles([$roleId]), [$meaning], ROCNIK);
    }

    /**
     * @param int[] $roleIds
     */
    private function userWithRoles(array $roleIds): User
    {
        $userId = $this->ucastnikVSql('role_repo_');
        foreach ($roleIds as $roleId) {
            $this->connection()->executeStatement(
                'INSERT INTO uzivatele_role (id_uzivatele, id_role) VALUES (:user, :role)',
                [
                    'user' => $userId,
                    'role' => $roleId,
                ],
            );
        }
        $user = $this->entityManager()->find(User::class, $userId);
        self::assertNotNull($user);

        return $user;
    }
}
