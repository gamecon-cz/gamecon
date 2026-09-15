<?php

declare(strict_types=1);

namespace App\State\Cart;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Cart\EntryFeeOutputDto;
use App\Dto\Cart\SetEntryFeeInputDto;
use App\Entity\User;
use App\Service\CurrentYearProviderInterface;
use App\Service\EntryFeeService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProcessorInterface<SetEntryFeeInputDto, EntryFeeOutputDto>
 */
readonly class SetEntryFeeProcessor implements ProcessorInterface
{
    public function __construct(
        private EntryFeeService $entryFeeService,
        private CurrentYearProviderInterface $currentYearProvider,
        private Security $security,
    ) {
    }

    /**
     * @param SetEntryFeeInputDto $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): EntryFeeOutputDto
    {
        $user = $this->security->getUser();
        if (! $user instanceof User) {
            throw new AccessDeniedHttpException('Pro uložení dobrovolného vstupného je nutné přihlášení.');
        }

        if ($data->amount === null) {
            throw new BadRequestHttpException('Částka musí být vyplněna.');
        }

        return EntryFeeOutputDto::fromAmount(
            $this->entryFeeService->setAmount($user, $data->amount),
            $this->currentYearProvider->getCurrentYear() - 1,
            $this->entryFeeService->lastYearAveragePercent(),
        );
    }
}
