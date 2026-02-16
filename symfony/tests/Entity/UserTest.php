<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Role;
use App\Entity\User;
use App\Entity\UserRole;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testGetRoleCodesReturnsEmptyArrayWhenNoRoles(): void
    {
        $user = new User();

        $this->assertSame([], $user->getRoleCodes());
    }

    public function testGetUserRolesReturnsEmptyCollectionWhenNoRoles(): void
    {
        $user = new User();

        $collection = $user->getUserRoles();

        $this->assertCount(0, $collection);
    }

    public function testGetRoleCodesReturnsSingleRole(): void
    {
        $user = new User();

        $role = new Role();
        $role->setKodRole('ADMIN');

        $userRole = new UserRole();
        $userRole->setRole($role);
        $userRole->setUser($user); // This automatically adds the UserRole to the user

        $this->assertSame(['ADMIN'], $user->getRoleCodes());
    }

    public function testGetRoleCodesReturnsMultipleRoles(): void
    {
        $user = new User();

        $role1 = new Role();
        $role1->setKodRole('ADMIN');

        $role2 = new Role();
        $role2->setKodRole('VYPRAVECSKA_SKUPINA');

        $userRole1 = new UserRole();
        $userRole1->setRole($role1);
        $userRole1->setUser($user);

        $userRole2 = new UserRole();
        $userRole2->setRole($role2);
        $userRole2->setUser($user);

        $roleCodes = $user->getRoleCodes();

        // Sort for consistent comparison
        sort($roleCodes);
        $expected = ['ADMIN', 'VYPRAVECSKA_SKUPINA'];
        sort($expected);

        $this->assertSame($expected, $roleCodes);
    }

    public function testSetUserAutomaticallyAddsRoleToUser(): void
    {
        $user = new User();

        $role = new Role();
        $role->setKodRole('ADMIN');

        $userRole = new UserRole();
        $userRole->setRole($role);

        // Initially user has no roles
        $this->assertCount(0, $user->getUserRoles());

        // After setting the user, the UserRole should be automatically added
        $userRole->setUser($user);

        $this->assertCount(1, $user->getUserRoles());
        $this->assertTrue($user->getUserRoles()->contains($userRole));
    }

    public function testAddUserRoleDoesNotAddDuplicates(): void
    {
        $user = new User();

        $role = new Role();
        $role->setKodRole('ADMIN');

        $userRole = new UserRole();
        $userRole->setRole($role);
        $userRole->setUser($user);

        // Try to add the same UserRole again
        $user->addUserRole($userRole);

        // Should still only have one UserRole
        $this->assertCount(1, $user->getUserRoles());
    }
}
