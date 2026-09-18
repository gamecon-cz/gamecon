<?php

declare(strict_types=1);

namespace App\State\Admin;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Admin\SetCustomerAccommodationInputDto;
use App\Dto\Cart\AccommodationOutputDto;
use App\Entity\User;
use App\Service\AccommodationRules;
use App\Service\AccommodationWriter;
use App\Service\CurrentYearProviderInterface;
use App\Service\CustomerDeskRights;
use App\Service\LegacySessionService;
use App\State\Cart\AccommodationGridInterface;
use Doctrine\ORM\EntityManagerInterface;
use Gamecon\Pravo;
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
 * @implements ProcessorInterface<SetCustomerAccommodationInputDto, AccommodationOutputDto>
 */
readonly class SetCustomerAccommodationProcessor implements ProcessorInterface
{
    public function __construct(
        private AccommodationWriter $accommodationWriter,
        private AccommodationRules $accommodationRules,
        private CurrentYearProviderInterface $currentYearProvider,
        private CustomerDeskRights $deskRights,
        private LegacySessionService $legacySession,
        private AccommodationGridInterface $accommodationGrid,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param SetCustomerAccommodationInputDto $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AccommodationOutputDto
    {
        $operator = $this->deskRights->verifyOperator('Objednávání za účastníka');

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
                // Rezervace patří zákazníkovi, ne obsluze: pult objednává za něj, takže
                // rozhoduje, jestli je organizátor on. Okruh rolí je tentýž jako u merche.
                jeOrganizator: $customer->isOrganizer(),
            );
        } catch (\RuntimeException $chyba) {
            throw new BadRequestHttpException($chyba->getMessage(), $chyba);
        }

        // Both are stale after the write, for different reasons: save() clears the entity
        // manager, and it updates uzivatele_hodnoty by raw SQL, which the legacy object in
        // memory never sees. Resolve both again rather than reason about which still holds.
        $customer = $this->entityManager->find(User::class, $data->customerId);
        $legacyCustomer = $this->legacySession->getUserById($data->customerId);
        if ($customer === null || $legacyCustomer === null) {
            throw new BadRequestHttpException(sprintf('Uživatel s ID %d nebyl nalezen.', $data->customerId));
        }

        // The grid redraws from what came back, so it cannot drift from what was stored.
        return $this->accommodationGrid->forCustomer($customer, $legacyCustomer);
    }
}
