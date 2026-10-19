<?php

declare(strict_types=1);

namespace App\State\Cart;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Cart\AccommodationOutputDto;
use App\Dto\Cart\SetAccommodationInputDto;
use App\Entity\User;
use App\Exception\NoLongerAvailableException;
use App\Service\AccommodationRules;
use App\Service\AccommodationWriter;
use App\Service\BreakfastCanceller;
use App\Service\CurrentYearProviderInterface;
use App\Service\LegacySessionService;
use App\Service\UserPermissions;
use Gamecon\Pravo;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @implements ProcessorInterface<SetAccommodationInputDto, AccommodationOutputDto>
 */
readonly class SetAccommodationProcessor implements ProcessorInterface
{
    public function __construct(
        private AccommodationWriter $accommodationWriter,
        private BreakfastCanceller $breakfastCanceller,
        private AccommodationRules $accommodationRules,
        private UserPermissions $userPermissions,
        private AccommodationProvider $accommodationProvider,
        private CurrentYearProviderInterface $currentYearProvider,
        private LegacySessionService $legacySession,
        private Security $security,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param SetAccommodationInputDto $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AccommodationOutputDto
    {
        $user = $this->security->getUser();
        if (! $user instanceof User) {
            throw new AccessDeniedHttpException($this->translator->trans('accommodation.login_required_to_order', [], 'errors'));
        }

        // The grid returned at the end needs the legacy user, so refuse before writing
        // rather than save the nights and then answer 403.
        if ($this->legacySession->getCurrentUser() === null) {
            throw new AccessDeniedHttpException($this->translator->trans('accommodation.gamecon_registration_required', [], 'errors'));
        }

        if (SystemoveNastaveni::zGlobals()->prodejUbytovaniUkoncen()) {
            throw new NoLongerAvailableException($this->translator->trans('accommodation.sale_ended', [], 'errors'));
        }

        $year = $this->currentYearProvider->getCurrentYear();
        $this->accommodationWriter->save(
            $user,
            array_map('intval', $data->variantIds),
            $year,
            $this->userPermissions->has($user, Pravo::UBYTOVANI_MUZE_OBJEDNAT_JEDNU_NOC, $year),
            $data->roommate,
            $data->declined,
            $this->accommodationRules->sleepingBagsOnly($user, $year),
            jeOrganizator: $user->isOrganizer(),
            maySundayNight: $this->accommodationRules->maySundayNight($user, $year),
        );

        // After the nights, so a night booked in the same request cancels again what it
        // covers instead of the restore silently undoing it.
        if ($data->restoreBreakfasts) {
            $this->breakfastCanceller->restore($user, $year);
        }

        return $this->accommodationProvider->provide($operation, $uriVariables, $context);
    }
}
