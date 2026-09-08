<?php

declare(strict_types=1);

namespace App\State\Cart;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Cart\MerchProductOutputDto;
use App\Entity\Product;
use App\Entity\User;
use App\Enum\ProductTagCode;
use App\Enum\RoleMeaning;
use App\Repository\OrderItemRepository;
use App\Repository\ProductRepository;
use App\Service\CurrentYearProviderInterface;
use App\Service\DiscountCalculator;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProviderInterface<MerchProductOutputDto>
 */
readonly class MerchProductsProvider implements ProviderInterface
{
    public function __construct(
        private ProductRepository $productRepository,
        private OrderItemRepository $orderItemRepository,
        private DiscountCalculator $discountCalculator,
        private CurrentYearProviderInterface $currentYearProvider,
        private Security $security,
    ) {
    }

    /**
     * @return MerchProductOutputDto[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        if (! $user instanceof User) {
            throw new AccessDeniedHttpException('Pro zobrazení merche je nutné přihlášení.');
        }

        $year = $this->currentYearProvider->getCurrentYear();
        // The whole section locks on a date, independent of any single product's state.
        $prodejUkoncen = SystemoveNastaveni::zGlobals()->prodejPredmetuBezTricekUkoncen();
        $roleMeanings = $user->getRoleMeanings();
        $merch = [];

        foreach ($this->productRepository->findByTag(ProductTagCode::PREDMET) as $product) {
            // Hoodies carry PREDMET *and* MIKINA, and are still sold by the legacy form.
            // Offering them here too would let the form's diff delete what the grid added.
            if ($product->hasTag(ProductTagCode::MIKINA->value)) {
                continue;
            }

            $dto = $this->toDto($product, $user, $year, $prodejUkoncen, $roleMeanings);
            if ($dto !== null) {
                $merch[] = $dto;
            }
        }

        return $merch;
    }

    /**
     * @param RoleMeaning[] $roleMeanings
     */
    private function toDto(Product $product, User $user, int $year, bool $prodejUkoncen, array $roleMeanings): ?MerchProductOutputDto
    {
        $variant = $product->getVariants()->first();
        if ($variant === false || $variant->getId() === null) {
            return null;
        }

        $purchasedQuantity = $this->orderItemRepository->countCustomerPurchases($user, $product, $year);
        // isPublic() also covers an expired nabizet_do, which the legacy shop treats as
        // a suspended product rather than a public one.
        $available = ! $prodejUkoncen && $product->isPublic();

        // A sold-out or withdrawn product still has to appear once the customer owns one,
        // otherwise their basket silently loses it.
        if (! $available && $purchasedQuantity === 0) {
            return null;
        }

        $discount = $this->discountCalculator->calculateDiscount($product, $user, $year);

        $dto = new MerchProductOutputDto();
        $dto->name = $product->getName();
        $dto->description = $product->getDescription();
        $dto->variantId = $variant->getId();
        $dto->price = $product->getCurrentPrice();
        $dto->discountedPrice = $discount['finalPrice'];
        $dto->purchasedQuantity = $purchasedQuantity;
        $dto->maxQuantity = $this->maxQuantity($product, $purchasedQuantity, $roleMeanings);
        $dto->secondary = $product->isSecondary();
        $dto->available = $available;

        return $dto;
    }

    /**
     * Mirrors what CapacityManager::purchase() will actually allow, so the grid does not
     * offer a piece the next click would be rejected for. Their own pieces count as sold
     * already, hence adding them back.
     *
     * @param RoleMeaning[] $roleMeanings
     */
    private function maxQuantity(Product $product, int $purchasedQuantity, array $roleMeanings): ?int
    {
        $variant = $product->getVariants()->first();
        if ($variant === false) {
            return null;
        }

        $remaining = $variant->getRemainingQuantity();
        if ($remaining === null) {
            return null;
        }

        if (! RoleMeaning::anyIsOrganizer($roleMeanings)) {
            $remaining -= $variant->getEffectiveReservedForOrganizers() ?? 0;
        }

        return max(0, $remaining) + $purchasedQuantity;
    }
}
