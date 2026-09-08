<?php

declare(strict_types=1);

namespace App\State\Cart;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Cart\AccommodationCellOutputDto;
use App\Dto\Cart\AccommodationDayOutputDto;
use App\Dto\Cart\AccommodationOutputDto;
use App\Dto\Cart\AccommodationTypeOutputDto;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\ProductTagCode;
use App\Repository\OrderItemRepository;
use App\Repository\ProductRepository;
use App\Service\CurrentYearProviderInterface;
use App\Service\DiscountCalculator;
use App\Service\LegacySessionService;
use Gamecon\Pravo;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Serves the logged-in customer's own grid. Legacy tells the occupant apart from the
 * orderer (an infopult worker books for someone else and their own organizer status then
 * unlocks Sunday); this endpoint has no such parameter, so ordering on behalf of another
 * person stays on the legacy form for now.
 *
 * @implements ProviderInterface<AccommodationOutputDto>
 */
readonly class AccommodationProvider implements ProviderInterface
{
    private const NAZVY_DNU = ['středa', 'čtvrtek', 'pátek', 'sobota', 'neděle'];

    private const DEN_NEDELE = 4;

    public function __construct(
        private ProductRepository $productRepository,
        private OrderItemRepository $orderItemRepository,
        private DiscountCalculator $discountCalculator,
        private CurrentYearProviderInterface $currentYearProvider,
        private LegacySessionService $legacySession,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AccommodationOutputDto
    {
        $user = $this->security->getUser();
        if (! $user instanceof User) {
            throw new AccessDeniedHttpException('Pro zobrazení ubytování je nutné přihlášení.');
        }

        $year = $this->currentYearProvider->getCurrentYear();
        $nastaveni = SystemoveNastaveni::zGlobals();
        $prodejUkoncen = $nastaveni->prodejUbytovaniUkoncen();
        $legacyUzivatel = $this->legacySession->getCurrentUser();

        $muzeNedeli = $legacyUzivatel !== null && (
            $legacyUzivatel->maPravo(Pravo::UBYTOVANI_NEDELNI_NOC_NABIZET)
            || $legacyUzivatel->maPravo(Pravo::UBYTOVANI_NEDELNI_NOC_ZDARMA)
            || $legacyUzivatel->jeOrganizator()
        );
        $muzeJednuNoc = $legacyUzivatel !== null
            && $legacyUzivatel->maPravo(Pravo::UBYTOVANI_MUZE_OBJEDNAT_JEDNU_NOC);
        $jeOrganizator = $legacyUzivatel !== null && $legacyUzivatel->jeOrganizator();

        $dto = new AccommodationOutputDto();
        $dto->saleClosed = $prodejUkoncen;
        $dto->minimumNights = $muzeJednuNoc ? 1 : 2;
        $dto->roommate = $legacyUzivatel?->ubytovanS() ?: null;
        $dto->declined = (bool) $legacyUzivatel?->nechceUbytovani();

        foreach (self::NAZVY_DNU as $den => $nazev) {
            if ($den === self::DEN_NEDELE && ! $muzeNedeli) {
                continue;
            }
            $denDto = new AccommodationDayOutputDto();
            $denDto->day = $den;
            $denDto->name = $nazev;
            $dto->days[] = $denDto;
        }

        [$koupeneVarianty, $koupeneDny] = $this->koupeneNoci($user, $year);
        $dto->selectedVariantIds = array_values($koupeneVarianty);

        // A night the customer already holds always gets a cell, even on a day they could
        // not newly order — otherwise the booking they own is invisible and the write path
        // would drop it, leaving the rest non-consecutive.
        if (! $muzeNedeli && in_array(self::DEN_NEDELE, $koupeneDny, true)) {
            $denDto = new AccommodationDayOutputDto();
            $denDto->day = self::DEN_NEDELE;
            $denDto->name = self::NAZVY_DNU[self::DEN_NEDELE];
            $dto->days[] = $denDto;
        }

        $viditelneDny = array_column($dto->days, 'day');
        $prodano = $this->prodanoPoVariantach($year);

        foreach ($this->productRepository->findByTag(ProductTagCode::UBYTOVANI) as $product) {
            $typDto = $this->toTypeDto(
                $product, $user, $year, $viditelneDny, $prodejUkoncen, $koupeneVarianty, $prodano, $jeOrganizator,
            );
            if ($typDto !== null) {
                $dto->types[] = $typDto;
            }
        }

        return $dto;
    }

    /**
     * @param int[]          $viditelneDny
     * @param int[]          $koupeneVarianty
     * @param array<int,int> $prodano         sold count per variant id
     */
    private function toTypeDto(
        Product $product,
        User $user,
        int $year,
        array $viditelneDny,
        bool $prodejUkoncen,
        array $koupeneVarianty,
        array $prodano,
        bool $jeOrganizator,
    ): ?AccommodationTypeOutputDto {
        // The nights absorbed by the day-variant migration are still products in their own
        // right — the legacy form reads them — but they carry no variants and must not
        // appear as rows of their own here.
        $variants = $product->getVariants();
        if ($variants->isEmpty()) {
            return null;
        }

        // Legacy offers a night only when the product is VEREJNY and still within
        // nabizet_do; isPublic() covers both. A RESTRICTED type stays visible so an
        // existing booking can be seen, but no new night can be ticked.
        $nabizeno = $product->isPublic();

        $discount = $this->discountCalculator->calculateDiscount($product, $user, $year);

        $typDto = new AccommodationTypeOutputDto();
        $typDto->productId = (int) $product->getId();
        $typDto->name = $product->getName();
        $typDto->description = $product->getDescription();
        $typDto->price = $product->getCurrentPrice();
        $typDto->discountedPrice = $discount['finalPrice'];

        foreach ($variants as $variant) {
            $den = $variant->getAccommodationDay();
            if ($den === null || ! in_array($den, $viditelneDny, true) || $variant->getId() === null) {
                continue;
            }

            // remaining_quantity still holds the produced count for anything the legacy
            // form sells, so derive what is left the way legacy does.
            $kapacita = $variant->getRemainingQuantity();
            $zbyva = $kapacita === null
                ? null
                : max(0, $kapacita - ($prodano[$variant->getId()] ?? 0) - $this->rezervovanoProOrganizatory($variant, $jeOrganizator));
            $vyprodano = $zbyva !== null && $zbyva <= 0;

            $koupeno = in_array($variant->getId(), $koupeneVarianty, true);

            $cell = new AccommodationCellOutputDto();
            $cell->variantId = $variant->getId();
            $cell->selected = $koupeno;
            $cell->remaining = $zbyva;
            $cell->soldOut = $vyprodano;
            // A night already booked stays selectable, otherwise the customer could not
            // drop it — the same reason the legacy grid never disables a ticked box.
            $cell->locked = ! $koupeno && ($prodejUkoncen || $vyprodano || ! $nabizeno);

            $typDto->nights[$den] = $cell;
        }

        return $typDto->nights === [] ? null : $typDto;
    }

    /**
     * Organizer-reserved places are not on offer to anyone else, mirroring what
     * CapacityManager::purchase() enforces on the write path.
     */
    private function rezervovanoProOrganizatory(ProductVariant $variant, bool $jeOrganizator): int
    {
        return $jeOrganizator ? 0 : ($variant->getEffectiveReservedForOrganizers() ?? 0);
    }

    /**
     * @return array<int, int> sold count per accommodation variant id
     */
    private function prodanoPoVariantach(int $year): array
    {
        $variantIds = [];
        foreach ($this->productRepository->findByTag(ProductTagCode::UBYTOVANI) as $product) {
            foreach ($product->getVariants() as $variant) {
                if ($variant->getId() !== null) {
                    $variantIds[] = $variant->getId();
                }
            }
        }

        return $this->orderItemRepository->countSoldByVariant($variantIds, $year);
    }

    /**
     * @return array{0: int[], 1: int[]} variant ids and day indexes of this year's
     *                                   accommodation the customer already holds
     */
    private function koupeneNoci(User $user, int $year): array
    {
        $varianty = [];
        $dny = [];
        foreach ($this->orderItemRepository->findByCustomerAndYear($user, $year) as $item) {
            $variant = $item->getVariant();
            if ($variant === null || $variant->getAccommodationDay() === null) {
                continue;
            }
            // The tag check is what actually selects accommodation: meals carry
            // accommodation_day too (it doubles as "which festival day"), so filtering on
            // that alone would count a bought breakfast as a booked night.
            if (! $variant->getProduct()->hasTag(ProductTagCode::UBYTOVANI->value)) {
                continue;
            }
            $varianty[] = (int) $variant->getId();
            $dny[] = (int) $variant->getAccommodationDay();
        }

        return [$varianty, array_unique($dny)];
    }
}
