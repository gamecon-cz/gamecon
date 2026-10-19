<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\UserRole;
use App\Service\CurrentYearProviderInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

/**
 * Doctrine entity listener that triggers cart recalculation
 * when a UserRole is added or removed via Doctrine.
 */
#[AsEntityListener(event: Events::postPersist, entity: UserRole::class)]
#[AsEntityListener(event: Events::postRemove, entity: UserRole::class)]
class UserRoleEntityListener
{
    public function __construct(
        private readonly UserRoleChangedListener $roleChangedListener,
        private readonly CurrentYearProviderInterface $currentYearProvider,
    ) {
    }

    public function postPersist(UserRole $userRole): void
    {
        $this->onRoleChanged($userRole);
    }

    public function postRemove(UserRole $userRole): void
    {
        $this->onRoleChanged($userRole);
    }

    private function onRoleChanged(UserRole $userRole): void
    {
        $user = $userRole->getUser();
        $year = $this->currentYearProvider->getCurrentYear();

        $this->roleChangedListener->onUserRoleChanged($user, $year);
    }
}
