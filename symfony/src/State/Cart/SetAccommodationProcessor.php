<?php

declare(strict_types=1);

namespace App\State\Cart;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Cart\AccommodationOutputDto;
use App\Dto\Cart\SetAccommodationInputDto;
use App\Entity\User;
use App\Service\AccommodationWriter;
use App\Service\CurrentYearProviderInterface;
use App\Service\LegacySessionService;
use Gamecon\Pravo;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProcessorInterface<SetAccommodationInputDto, AccommodationOutputDto>
 */
readonly class SetAccommodationProcessor implements ProcessorInterface
{
    public function __construct(
        private AccommodationWriter $accommodationWriter,
        private AccommodationProvider $accommodationProvider,
        private CurrentYearProviderInterface $currentYearProvider,
        private LegacySessionService $legacySession,
        private Security $security,
    ) {
    }

    /**
     * @param SetAccommodationInputDto $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AccommodationOutputDto
    {
        $user = $this->security->getUser();
        if (! $user instanceof User) {
            throw new AccessDeniedHttpException('Pro objednání ubytování je nutné přihlášení.');
        }

        // The same reason the read endpoint refuses: every accommodation right lives in the
        // legacy permission system, so without that session an organizer silently loses one.
        $legacyUzivatel = $this->legacySession->getCurrentUser();
        if ($legacyUzivatel === null) {
            throw new AccessDeniedHttpException('Ubytování vyžaduje přihlášení na webu GameConu.');
        }

        if (SystemoveNastaveni::zGlobals()->prodejUbytovaniUkoncen()) {
            throw new BadRequestHttpException('Prodej ubytování už skončil.');
        }

        try {
            $this->accommodationWriter->save(
                $user,
                array_map('intval', $data->variantIds),
                $this->currentYearProvider->getCurrentYear(),
                $legacyUzivatel->maPravo(Pravo::UBYTOVANI_MUZE_OBJEDNAT_JEDNU_NOC),
            );
        } catch (\RuntimeException $chyba) {
            throw new BadRequestHttpException($chyba->getMessage(), $chyba);
        }

        return $this->accommodationProvider->provide($operation, $uriVariables, $context);
    }
}
