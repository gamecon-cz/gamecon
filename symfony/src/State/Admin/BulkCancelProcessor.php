<?php

declare(strict_types=1);

namespace App\State\Admin;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Admin\BulkCancelInputDto;
use App\Dto\Admin\BulkCancelOutputDto;
use App\Entity\User;
use App\Service\BulkCancelService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProcessorInterface<BulkCancelInputDto, BulkCancelOutputDto>
 */
readonly class BulkCancelProcessor implements ProcessorInterface
{
    public function __construct(
        private BulkCancelService $bulkCancelService,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BulkCancelOutputDto
    {
        $users = [];
        foreach ($data->userIds as $userId) {
            $user = $this->entityManager->find(User::class, $userId);
            if ($user === null) {
                throw new BadRequestHttpException(sprintf('Uživatel s ID %d nebyl nalezen.', $userId));
            }
            $users[] = $user;
        }

        $cancelledAt = $this->clock->now();
        $totalCancelled = 0;

        if ($data->tag !== null) {
            $totalCancelled = $this->bulkCancelService->cancelByTagForUsers(
                $users,
                $data->tag,
                $data->reason,
                $cancelledAt,
            );
        } else {
            foreach ($users as $user) {
                $totalCancelled += $this->bulkCancelService->cancelAllForUser(
                    $user,
                    $data->reason,
                    $cancelledAt,
                );
            }
        }

        return new BulkCancelOutputDto(
            cancelledCount: $totalCancelled,
            usersAffected: count($users),
            reason: $data->reason,
        );
    }
}
