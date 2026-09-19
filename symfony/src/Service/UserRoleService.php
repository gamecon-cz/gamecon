<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Role;
use App\Entity\User;
use App\Entity\UserRole;
use App\Entity\UserRoleLog;
use App\Enum\RoleChangeType;
use App\Repository\UserRoleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * UserRoleService — manages user role assignments via Doctrine.
 *
 * Replaces direct SQL in legacy Uzivatel::pridejRoli()/odeberRoli().
 * Using Doctrine entities triggers entity listeners (e.g. cart recalculation).
 * Handles logging (UserRoleLog) and history recalculation (RoleHistoryRecalculator).
 */
class UserRoleService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRoleRepository $userRoleRepository,
        private readonly ValidatorInterface $validator,
        private readonly CurrentYearProviderInterface $currentYearProvider,
        private readonly RoleHistoryRecalculator $roleHistoryRecalculator,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Assign a role to a user.
     *
     * @return bool True if the role was newly assigned (false if user already had it)
     *
     * @throws ValidationFailedException if unique role constraint is violated
     */
    public function assignRole(User $user, Role $role, ?User $assignedBy = null): bool
    {
        // Check if user already has this role
        if ($this->hasRole($user, $role)) {
            return false;
        }

        $userRole = new UserRole();
        $userRole->setUser($user);
        $userRole->setRole($role);
        $userRole->setPosazen(new \DateTime());
        $userRole->setGivenBy($assignedBy);

        // Validate (triggers UniqueRoleConstraint)
        $violations = $this->validator->validate($userRole);
        if ($violations->count() > 0) {
            throw new ValidationFailedException($userRole, $violations);
        }

        // Persist UserRole → triggers Doctrine entity listener → cart recalculation
        $this->entityManager->persist($userRole);
        $this->entityManager->flush();

        // Log the change (replaces legacy zalogujZmenuRole)
        $this->logRoleChange($user, $role, $assignedBy, RoleChangeType::ASSIGNED);

        // Recalculate role history (replaces legacy RolePodleRocniku)
        $year = $this->currentYearProvider->getCurrentYear();
        $this->roleHistoryRecalculator->recalculate($year, $user->getId());

        $this->logger->info('Role assigned', [
            'user_id'      => $user->getId(),
            'role_id'      => $role->getId(),
            'role_meaning' => $role->getVyznamRole()->value,
            'assigned_by'  => $assignedBy?->getId(),
        ]);

        return true;
    }

    /**
     * Remove a role from a user.
     *
     * @return bool True if the role was removed (false if user didn't have it)
     */
    public function removeRole(User $user, Role $role, ?User $removedBy = null): bool
    {
        $userRole = $this->userRoleRepository->findOneBy([
            'user' => $user,
            'role' => $role,
        ]);

        if ($userRole === null) {
            return false;
        }

        $user->removeUserRole($userRole);
        $this->entityManager->remove($userRole);
        $this->entityManager->flush();

        // Log the change
        $this->logRoleChange($user, $role, $removedBy, RoleChangeType::REMOVED);

        // Recalculate role history
        $year = $this->currentYearProvider->getCurrentYear();
        $this->roleHistoryRecalculator->recalculate($year, $user->getId());

        $this->logger->info('Role removed', [
            'user_id'      => $user->getId(),
            'role_id'      => $role->getId(),
            'role_meaning' => $role->getVyznamRole()->value,
            'removed_by'   => $removedBy?->getId(),
        ]);

        return true;
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole(User $user, Role $role): bool
    {
        return $this->userRoleRepository->findOneBy([
            'user' => $user,
            'role' => $role,
        ]) !== null;
    }

    private function logRoleChange(User $user, Role $role, ?User $changedBy, RoleChangeType $changeType): void
    {
        $log = new UserRoleLog();
        $log->setUser($user);
        $log->setRole($role);
        $log->setChangedBy($changedBy);
        $log->setZmena($changeType->value);
        $log->setKdy(new \DateTime());

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }
}
