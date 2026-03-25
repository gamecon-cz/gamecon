<?php

declare(strict_types=1);

namespace App\Tests\Validator;

use App\Entity\Role;
use App\Entity\User;
use App\Entity\UserRole;
use App\Enum\RoleMeaning;
use App\Repository\RolePermissionRepository;
use App\Repository\UserRoleRepository;
use App\Validator\UniqueRoleConstraint;
use App\Validator\UniqueRoleConstraintValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class UniqueRoleConstraintValidatorTest extends TestCase
{
    private MockObject $rolePermissionRepository;

    private MockObject $userRoleRepository;

    private MockObject $context;

    private UniqueRoleConstraintValidator $validator;

    protected function setUp(): void
    {
        $this->rolePermissionRepository = $this->createMock(RolePermissionRepository::class);
        $this->userRoleRepository = $this->createMock(UserRoleRepository::class);
        $this->context = $this->createMock(ExecutionContextInterface::class);

        $this->validator = new UniqueRoleConstraintValidator(
            $this->rolePermissionRepository,
            $this->userRoleRepository,
        );
        $this->validator->initialize($this->context);
    }

    public function testNonUniqueRolePassesValidation(): void
    {
        $userRole = $this->createUserRole(RoleMeaning::HERMAN, 1);

        // New role does NOT have UNIKATNI_ROLE permission
        $this->rolePermissionRepository->method('roleHasPermission')
            ->willReturn(false);

        $this->context->expects($this->never())->method('buildViolation');

        $this->validator->validate($userRole, new UniqueRoleConstraint());
    }

    public function testUniqueRoleWithNoExistingUniqueRolesPassesValidation(): void
    {
        $userRole = $this->createUserRole(RoleMeaning::ORGANIZATOR_ZDARMA, 1);

        // New role HAS UNIKATNI_ROLE permission
        $this->rolePermissionRepository->method('roleHasPermission')
            ->willReturn(true);

        // User has no existing roles
        $this->userRoleRepository->method('findBy')
            ->willReturn([]);

        $this->context->expects($this->never())->method('buildViolation');

        $this->validator->validate($userRole, new UniqueRoleConstraint());
    }

    public function testUniqueRoleWithExistingNonUniqueRolesPassesValidation(): void
    {
        $userRole = $this->createUserRole(RoleMeaning::ORGANIZATOR_ZDARMA, 1);

        // Track which roles are checked
        $this->rolePermissionRepository->method('roleHasPermission')
            ->willReturnCallback(function (Role $role) {
                // New role (id=1) has unique permission, existing role (id=2) does not
                return $role->getId() === 1;
            });

        // User has one existing role without unique permission
        $existingUserRole = $this->createUserRole(RoleMeaning::HERMAN, 2);
        $this->userRoleRepository->method('findBy')
            ->willReturn([$existingUserRole]);

        $this->context->expects($this->never())->method('buildViolation');

        $this->validator->validate($userRole, new UniqueRoleConstraint());
    }

    public function testUniqueRoleViolationWhenUserAlreadyHasUniqueRole(): void
    {
        $userRole = $this->createUserRole(RoleMeaning::PUL_ORG_UBYTKO, 2);

        // Both roles have UNIKATNI_ROLE permission
        $this->rolePermissionRepository->method('roleHasPermission')
            ->willReturn(true);

        // User already has a unique role (different id)
        $existingUserRole = $this->createUserRole(RoleMeaning::ORGANIZATOR_ZDARMA, 1);
        $this->userRoleRepository->method('findBy')
            ->willReturn([$existingUserRole]);

        $violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $violationBuilder->expects($this->once())->method('addViolation');
        $this->context->expects($this->once())
            ->method('buildViolation')
            ->with('Uživatel už má jinou unikátní roli.')
            ->willReturn($violationBuilder);

        $this->validator->validate($userRole, new UniqueRoleConstraint());
    }

    public function testReassigningSameRolePassesValidation(): void
    {
        $userRole = $this->createUserRole(RoleMeaning::ORGANIZATOR_ZDARMA, 1);

        $this->rolePermissionRepository->method('roleHasPermission')
            ->willReturn(true);

        // User's existing role has the same ID → it's the same role, skip
        $existingUserRole = $this->createUserRole(RoleMeaning::ORGANIZATOR_ZDARMA, 1);
        $this->userRoleRepository->method('findBy')
            ->willReturn([$existingUserRole]);

        $this->context->expects($this->never())->method('buildViolation');

        $this->validator->validate($userRole, new UniqueRoleConstraint());
    }

    private function createUserRole(RoleMeaning $meaning, int $roleId): UserRole
    {
        $role = new Role();
        $role->setId($roleId);
        $role->setKodRole('ROLE_' . $meaning->value);
        $role->setNazevRole($meaning->value);
        $role->setPopisRole('');
        $role->setRocnikRole(-1);
        $role->setTypRole('trvala');
        $role->setVyznamRole($meaning);

        $user = $this->createMock(User::class);

        $userRole = new UserRole();
        $userRole->setUser($user);
        $userRole->setRole($role);
        $userRole->setPosazen(new \DateTime());

        return $userRole;
    }
}
