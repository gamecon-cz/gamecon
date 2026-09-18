<?php

declare(strict_types=1);

namespace App\State\Cart;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Cart\MerchProductOutputDto;
use App\Entity\Product;
use App\Entity\User;
use App\Enum\ProductStateEnum;
use App\Enum\ProductTagCode;
use App\Enum\RoleMeaning;
use App\Repository\OrderItemRepository;
use App\Repository\ProductRepository;
use App\Service\CurrentYearProviderInterface;
use App\Service\DiscountCalculator;
use App\Service\ProductVariantsForGrid;
use App\Service\RestrictedProductRules;
use App\Service\SpentQuotaProvider;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Trička a mikiny. Od merche se liší třemi věcmi, kvůli kterým nesdílí provider:
 * mají dva různé termíny prodeje a orgovská a vypravěčská trička vyžadují právo.
 *
 * @implements ProviderInterface<MerchProductOutputDto>
 */
readonly class ShirtProductsProvider implements ProviderInterface
{
    public function __construct(
        private ProductRepository $productRepository,
        private OrderItemRepository $orderItemRepository,
        private DiscountCalculator $discountCalculator,
        private CurrentYearProviderInterface $currentYearProvider,
        private RestrictedProductRules $restrictedProductRules,
        private ProductVariantsForGrid $variantsForGrid,
        private SpentQuotaProvider $spentQuota,
        private Security $security,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return MerchProductOutputDto[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        if (! $user instanceof User) {
            throw new AccessDeniedHttpException('Pro zobrazení triček je nutné přihlášení.');
        }

        $year = $this->currentYearProvider->getCurrentYear();
        $nastaveni = SystemoveNastaveni::zGlobals();
        $roleMeanings = $user->getRoleMeanings();
        // Legacy uživatel stojí dotaz navíc, a když v nabídce žádné omezené tričko není,
        // není za co platit. Načte se proto až u prvního, a pak se drží.
        $legacyUser = false;
        // Jednou za request: mřížka mezi produkty nezapisuje, takže se spotřeba nemění.
        $spentQuota = $this->spentQuota->forUser($user, $year);

        $svrsky = [];
        foreach ($this->svrsky() as $product) {
            $prodejUkoncen = $product->hasTag(ProductTagCode::MIKINA->value)
                ? $nastaveni->prodejMikinUkoncen()
                : $nastaveni->prodejTricekUkoncen();

            if ($this->restrictedProductRules->isRestricted($product) && $legacyUser === false) {
                $legacyUser = $this->restrictedProductRules->legacyUserFor($user);
            }

            $dto = $this->toDto(
                $product,
                $user,
                $legacyUser === false ? null : $legacyUser,
                $year,
                $prodejUkoncen,
                $roleMeanings,
                $spentQuota,
            );
            if ($dto !== null) {
                $svrsky[] = $dto;
            }
        }

        return $svrsky;
    }

    /**
     * Mikina nese `predmet` i `mikina`, tričko `tricko` — dohromady jedna sekce.
     *
     * @return Product[]
     */
    private function svrsky(): array
    {
        $mikiny = array_filter(
            $this->productRepository->findByTag(ProductTagCode::PREDMET),
            static fn (Product $product): bool => $product->hasTag(ProductTagCode::MIKINA->value),
        );

        return [
            ...$this->productRepository->findByTag(ProductTagCode::TRICKO),
            ...$mikiny,
        ];
    }

    /**
     * @param RoleMeaning[]      $roleMeanings
     * @param array<string, int> $spentQuota
     */
    private function toDto(
        Product $product,
        User $user,
        ?\Uzivatel $legacyUser,
        int $year,
        bool $prodejUkoncen,
        array $roleMeanings,
        array $spentQuota,
    ): ?MerchProductOutputDto {
        // Omezené tričko se bez práva vůbec nenabízí — jinak by zákazník klikl a dostal
        // chybu z `CartService`, místo aby ho neviděl.
        if ($this->restrictedProductRules->isRestricted($product)) {
            if ($legacyUser === null || ! $this->restrictedProductRules->mayOrder($product, $legacyUser)) {
                return null;
            }
        }

        $purchasedQuantity = $this->orderItemRepository->countCustomerPurchases($user, $product, $year);
        // `isPublic()` je na omezené tričko krátké — má stav RESTRICTED, ne PUBLIC. Ptát se
        // ale musíme na stav, ne na tag: stažené (RETIRED) nebo prošlé tričko se nesmí
        // nabídnout ani tomu, kdo na barvu právo má.
        $stav = $product->getState();
        $available = ! $prodejUkoncen
            && $product->isAvailable($this->clock->now())
            && ($stav === ProductStateEnum::PUBLIC || $stav === ProductStateEnum::RESTRICTED);

        // Vyprodaný nebo stažený svršek musí zůstat vidět, jakmile ho zákazník má —
        // jinak by mu z přehledu zmizel.
        if (! $available && $purchasedQuantity === 0) {
            return null;
        }

        $variants = $this->variantsForGrid->pro($product, $user, $year, $roleMeanings);
        if ($variants === []) {
            return null;
        }

        $discount = $this->discountCalculator->calculateDiscount($product, $user, $year);

        $dto = new MerchProductOutputDto();
        $dto->name = $product->getName();
        $dto->code = $product->getCode();
        $dto->description = $product->getDescription();
        $dto->variants = $variants;
        $dto->price = $product->getCurrentPrice();
        $dto->discountedPrice = $discount['finalPrice'];
        $dto->purchasedQuantity = $purchasedQuantity;
        $dto->priceSteps = $this->discountCalculator->priceSteps(
            $product,
            $user,
            $year,
            $purchasedQuantity,
            $spentQuota,
        );
        $dto->secondary = $product->isSecondary();
        $dto->available = $available;

        return $dto;
    }
}
