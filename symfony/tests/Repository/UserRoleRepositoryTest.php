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
        $userId = $this->ucastnikVSql('role_repo_');
        $this->connection()->executeStatement(
            'INSERT INTO uzivatele_role (id_uzivatele, id_role) VALUES (:user, :role)',
            [
                'user' => $userId,
                'role' => $roleId,
            ],
        );
        $user = $this->entityManager()->find(User::class, $userId);
        self::assertNotNull($user);

        return static::getContainer()->get(UserRoleRepository::class)->holdsAnyMeaning($user, [$meaning], ROCNIK);
    }
}
