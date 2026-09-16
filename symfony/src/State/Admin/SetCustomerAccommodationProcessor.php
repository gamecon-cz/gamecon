<?php

declare(strict_types=1);

namespace App\State\Admin;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Admin\SetCustomerAccommodationInputDto;
use App\Entity\User;
use App\Service\AccommodationRules;
use App\Service\AccommodationWriter;
use App\Service\CurrentYearProviderInterface;
use App\Service\LegacySessionService;
use Doctrine\ORM\EntityManagerInterface;
use Gamecon\Pravo;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Booking accommodation for someone else, from the admin desk.
 *
 * Reading the booking rules from the security token, as the cart does, would apply the
 * operator's rights to the customer's booking — so the customer is named in the payload and
 * their rights are looked up separately from the operator's.
 *
 * The sale deadline is deliberately not checked: the desk books people in after it has
 * passed, which is most of what it is for.
 *
 * @implements ProcessorInterface<SetCustomerAccommodationInputDto, null>
 */
readonly class SetCustomerAccommodationProcessor implements ProcessorInterface
{
    public function __construct(
        private AccommodationWriter $accommodationWriter,
        private AccommodationRules $accommodationRules,
        private CurrentYearProviderInterface $currentYearProvider,
        private LegacySessionService $legacySession,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param SetCustomerAccommodationInputDto $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $operator = $this->verifyOperator();

        $customer = $this->entityManager->find(User::class, $data->customerId);
        if ($customer === null) {
            throw new BadRequestHttpException(sprintf('Uživatel s ID %d nebyl nalezen.', $data->customerId));
        }

        $legacyCustomer = $this->legacySession->getUserById((int) $customer->getId());
        if ($legacyCustomer === null) {
            throw new BadRequestHttpException(sprintf('Uživatel s ID %d nebyl nalezen.', $data->customerId));
        }

        // An omitted roommate keeps whatever is stored: the writer treats null as "clear it",
        // and the infopult screen has no such field to send.
        $roommate = $data->roommate ?? $legacyCustomer->ubytovanS();

        try {
            $this->accommodationWriter->save(
                $customer,
                array_map('intval', $data->variantIds),
                $this->currentYearProvider->getCurrentYear(),
                $legacyCustomer->maPravo(Pravo::UBYTOVANI_MUZE_OBJEDNAT_JEDNU_NOC),
                $roommate,
                $data->declined,
                $this->accommodationRules->sleepingBagsOnly($legacyCustomer),
                // Legacy offered this button to the infopult chief but never checked on write,
                // so a hand-made request overbooked for anyone. Now the server decides.
                mayOverbook: $operator->jeSefInfopultu(),
            );
        } catch (\RuntimeException $chyba) {
            throw new BadRequestHttpException($chyba->getMessage(), $chyba);
        }

        return null;
    }

    /**
     * The same rights the two admin screens declare in their module headers (100 and 101).
     * ROLE_ADMIN cannot stand in for them: it is granted by role code, and the codes that
     * carry these rights are per-year (`gc2026_infopult`), so it matches neither reliably.
     */
    private function verifyOperator(): \Uzivatel
    {
        $operator = $this->legacySession->getCurrentUser();
        if ($operator === null) {
            throw new AccessDeniedHttpException('Objednávání za účastníka vyžaduje přihlášení do adminu.');
        }

        if (! $operator->maPravo(Pravo::ADMINISTRACE_UBYTOVANI)
            && ! $operator->maPravo(Pravo::ADMINISTRACE_INFOPULT)
        ) {
            throw new AccessDeniedHttpException('Na objednávání ubytování za účastníka nemáš právo.');
        }

        return $operator;
    }
}
