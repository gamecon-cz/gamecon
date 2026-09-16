<?php

declare(strict_types=1);

namespace App\State\Admin;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Cart\AccommodationOutputDto;
use App\Entity\User;
use App\Service\CustomerDeskRights;
use App\Service\LegacySessionService;
use App\State\Cart\AccommodationGridInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * The accommodation grid for a participant the desk names, rather than for whoever is signed
 * in. What the grid may offer follows from the participant's rights, so reading them off the
 * session — as the cart endpoint does — would show the operator's nights instead.
 *
 * @implements ProviderInterface<AccommodationOutputDto>
 */
readonly class CustomerAccommodationProvider implements ProviderInterface
{
    public function __construct(
        private AccommodationGridInterface $accommodationGrid,
        private CustomerDeskRights $deskRights,
        private LegacySessionService $legacySession,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AccommodationOutputDto
    {
        $this->deskRights->verifyOperator('Zobrazení ubytování účastníka');

        // Cast only what is already a number: a repeated ?customerId arrives as an array and
        // would cast to 1, quietly answering for whoever that is.
        $requested = $context['filters']['customerId'] ?? null;
        if (! is_string($requested) && ! is_int($requested)) {
            throw new BadRequestHttpException('Musí být zadán právě jeden účastník.');
        }

        $customerId = filter_var($requested, FILTER_VALIDATE_INT);
        if ($customerId === false || $customerId <= 0) {
            throw new BadRequestHttpException('Musí být zadán účastník.');
        }

        $customer = $this->entityManager->find(User::class, $customerId);
        $legacyCustomer = $this->legacySession->getUserById($customerId);
        if ($customer === null || $legacyCustomer === null) {
            throw new BadRequestHttpException(sprintf('Uživatel s ID %d nebyl nalezen.', $customerId));
        }

        return $this->accommodationGrid->forCustomer($customer, $legacyCustomer);
    }
}
