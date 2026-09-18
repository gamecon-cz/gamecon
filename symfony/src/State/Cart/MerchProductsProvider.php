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
use App\Service\ProductVariantsForGrid;
use App\Service\SpentQuotaProvider;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Psr\Clock\ClockInterface;
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
            throw new AccessDeniedHttpException('Pro zobrazení merche je nutné přihlášení.');
        }

        $year = $this->currentYearProvider->getCurrentYear();
        // The whole section locks on a date, independent of any single product's state.
        $prodejUkoncen = SystemoveNastaveni::zGlobals()->prodejPredmetuBezTricekUkoncen();
        $roleMeanings = $user->getRoleMeanings();
        // Jednou za request: mřížka mezi produkty nezapisuje, takže se spotřeba nemění.
        $spentQuota = $this->spentQuota->forUser($user, $year);
        $merch = [];

        foreach ($this->productRepository->findByTag(ProductTagCode::PREDMET) as $product) {
            // Mikiny nesou PREDMET i MIKINA, ale prodávají se se svršky — mají vlastní
            // termín prodeje, který tenhle provider nezná. Viz ShirtProductsProvider.
            if ($product->hasTag(ProductTagCode::MIKINA->value)) {
                continue;
            }

            $dto = $this->toDto($product, $user, $year, $prodejUkoncen, $roleMeanings, $spentQuota);
            if ($dto !== null) {
                $merch[] = $dto;
            }
        }

        return $merch;
    }

    /**
     * @param RoleMeaning[]      $roleMeanings
     * @param array<string, int> $spentQuota
     */
    private function toDto(
        Product $product,
        User $user,
        int $year,
        bool $prodejUkoncen,
        array $roleMeanings,
        array $spentQuota,
    ): ?MerchProductOutputDto {
        $purchasedQuantity = $this->orderItemRepository->countCustomerPurchases($user, $product, $year);
        // isPublic() also covers an expired nabizet_do, which the legacy shop treats as
        // a suspended product rather than a public one.
        $available = ! $prodejUkoncen && $product->isPublic($this->clock->now());

        // A sold-out or withdrawn product still has to appear once the customer owns one,
        // otherwise their basket silently loses it.
        if (! $available && $purchasedQuantity === 0) {
            return null;
        }

        $discount = $this->discountCalculator->calculateDiscount($product, $user, $year);

        $variants = $this->variantsForGrid->pro($product, $user, $year, $roleMeanings);
        if ($variants === []) {
            return null;
        }

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
