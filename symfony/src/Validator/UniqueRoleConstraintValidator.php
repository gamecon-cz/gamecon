<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\Role;
use App\Entity\UserRole;
use App\Repository\RolePermissionRepository;
use App\Repository\UserRoleRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Checks that a user doesn't already have a role with UNIKATNI_ROLE permission
 * when assigning a new role that also has this permission.
 *
 * Legacy equivalent: Pravo::UNIKATNI_ROLE (ID 1027)
 */
class UniqueRoleConstraintValidator extends ConstraintValidator
{
    public const UNIKATNI_ROLE_PERMISSION_ID = 1027;

    public function __construct(
        private readonly RolePermissionRepository $rolePermissionRepository,
        private readonly UserRoleRepository $userRoleRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof UniqueRoleConstraint) {
            throw new UnexpectedTypeException($constraint, UniqueRoleConstraint::class);
        }

        if (! $value instanceof UserRole) {
            throw new UnexpectedValueException($value, UserRole::class);
        }

        $newRole = $value->getRole();

        // Does the new role grant UNIKATNI_ROLE permission?
        if (! $this->roleHasUniquePermission($newRole)) {
            return; // not a unique role, no constraint
        }

        // Does the user already have any other role with UNIKATNI_ROLE permission?
        $user = $value->getUser();
        $existingUserRoles = $this->userRoleRepository->findBy([
            'user' => $user,
        ]);

        foreach ($existingUserRoles as $existingUserRole) {
            $existingRole = $existingUserRole->getRole();

            // Skip the same role (in case of re-assignment)
            if ($existingRole->getId() === $newRole->getId()) {
                continue;
            }

            if ($this->roleHasUniquePermission($existingRole)) {
                $this->context->buildViolation($constraint->message)
                    ->addViolation();

                return;
            }
        }
    }

    private function roleHasUniquePermission(Role $role): bool
    {
        return $this->rolePermissionRepository->roleHasPermission(
            $role,
            self::UNIKATNI_ROLE_PERMISSION_ID,
        );
    }
}
