<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Role;
use App\Entity\User;
use App\Entity\UserRole;
use App\Entity\UserRoleLog;
use App\Enum\RoleChangeType;
use App\Enum\RoleMeaning;
use App\Repository\UserRoleRepository;
use App\Service\CurrentYearProviderInterface;
use App\Service\RoleHistoryRecalculator;
use App\Service\UserRoleService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UserRoleServiceTest extends TestCase
{
    private MockObject $entityManager;

    private MockObject $userRoleRepository;

    private MockObject $validator;

    private MockObject $yearProvider;

    private MockObject $historyRecalculator;

    private UserRoleService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->userRoleRepository = $this->createMock(UserRoleRepository::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->yearProvider = $this->createMock(CurrentYearProviderInterface::class);
        $this->yearProvider->method('getCurrentYear')->willReturn(2026);
        $this->historyRecalculator = $this->createMock(RoleHistoryRecalculator::class);

        $this->service = new UserRoleService(
            $this->entityManager,
            $this->userRoleRepository,
            $this->validator,
            $this->yearProvider,
            $this->historyRecalculator,
            new NullLogger(),
        );
    }

    public function testAssignRoleSuccess(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        $role = $this->createRole(RoleMeaning::VYPRAVEC);

        $this->userRoleRepository->method('findOneBy')->willReturn(null);
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());

        // Expect persist called twice: UserRole + UserRoleLog
        $persisted = [];
        $this->entityManager->expects($this->exactly(2))
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$persisted) {
                $persisted[] = $entity;
            });

        $this->historyRecalculator->expects($this->once())
            ->method('recalculate')
            ->with(2026, 1);

        $result = $this->service->assignRole($user, $role);

        $this->assertTrue($result);
        $this->assertInstanceOf(UserRole::class, $persisted[0]);
        $this->assertInstanceOf(UserRoleLog::class, $persisted[1]);
        $this->assertSame(RoleChangeType::ASSIGNED->value, $persisted[1]->getZmena());
    }

    public function testAssignRoleAlreadyHasIt(): void
    {
        $user = $this->createMock(User::class);
        $role = $this->createRole(RoleMeaning::VYPRAVEC);

        $existingUserRole = $this->createMock(UserRole::class);
        $this->userRoleRepository->method('findOneBy')->willReturn($existingUserRole);

        $this->entityManager->expects($this->never())->method('persist');

        $result = $this->service->assignRole($user, $role);

        $this->assertFalse($result);
    }

    public function testAssignRoleValidationFails(): void
    {
        $user = $this->createMock(User::class);
        $role = $this->createRole(RoleMeaning::ORGANIZATOR_ZDARMA);

        $this->userRoleRepository->method('findOneBy')->willReturn(null);

        $violation = $this->createMock(\Symfony\Component\Validator\ConstraintViolationInterface::class);
        $violations = new ConstraintViolationList([$violation]);
        $this->validator->method('validate')->willReturn($violations);

        $this->entityManager->expects($this->never())->method('persist');

        $this->expectException(ValidationFailedException::class);

        $this->service->assignRole($user, $role);
    }

    public function testRemoveRoleSuccess(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        $user->expects($this->once())->method('removeUserRole');

        $role = $this->createRole(RoleMeaning::VYPRAVEC);

        $existingUserRole = $this->createMock(UserRole::class);
        $this->userRoleRepository->method('findOneBy')->willReturn($existingUserRole);

        // Expect remove (UserRole) + persist (UserRoleLog)
        $this->entityManager->expects($this->once())->method('remove')->with($existingUserRole);
        $this->entityManager->expects($this->once())->method('persist')
            ->with($this->isInstanceOf(UserRoleLog::class));

        $this->historyRecalculator->expects($this->once())
            ->method('recalculate')
            ->with(2026, 1);

        $result = $this->service->removeRole($user, $role);

        $this->assertTrue($result);
    }

    public function testRemoveRoleNotAssigned(): void
    {
        $user = $this->createMock(User::class);
        $role = $this->createRole(RoleMeaning::VYPRAVEC);

        $this->userRoleRepository->method('findOneBy')->willReturn(null);
        $this->entityManager->expects($this->never())->method('remove');

        $result = $this->service->removeRole($user, $role);

        $this->assertFalse($result);
    }

    public function testHasRole(): void
    {
        $user = $this->createMock(User::class);
        $role = $this->createRole(RoleMeaning::ORGANIZATOR_ZDARMA);

        $this->userRoleRepository->method('findOneBy')
            ->willReturn($this->createMock(UserRole::class));

        $this->assertTrue($this->service->hasRole($user, $role));
    }

    private function createRole(RoleMeaning $meaning): Role
    {
        $role = new Role();
        $role->setId(1);
        $role->setKodRole('TEST_ROLE');
        $role->setNazevRole('Test Role');
        $role->setPopisRole('');
        $role->setRocnikRole(-1);
        $role->setTypRole('trvala');
        $role->setVyznamRole($meaning);

        return $role;
    }
}
