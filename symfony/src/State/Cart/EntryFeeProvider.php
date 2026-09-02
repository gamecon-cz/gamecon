<?php

declare(strict_types=1);

namespace App\State\Cart;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Cart\EntryFeeOutputDto;
use App\Entity\User;
use App\Service\CurrentYearProviderInterface;
use App\Service\EntryFeeService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProviderInterface<EntryFeeOutputDto>
 */
readonly class EntryFeeProvider implements ProviderInterface
{
    public function __construct(
        private EntryFeeService $entryFeeService,
        private CurrentYearProviderInterface $currentYearProvider,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): EntryFeeOutputDto
    {
        $user = $this->security->getUser();
        if (! $user instanceof User) {
            throw new AccessDeniedHttpException('Pro zobrazení dobrovolného vstupného je nutné přihlášení.');
        }

        return EntryFeeOutputDto::fromAmount(
            $this->entryFeeService->getAmount($user),
            $this->currentYearProvider->getCurrentYear() - 1,
            $this->entryFeeService->lastYearAveragePercent(),
        );
    }
}
