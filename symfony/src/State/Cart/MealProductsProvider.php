<?php

declare(strict_types=1);

namespace App\State\Cart;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Cart\MealProductOutputDto;
use App\Entity\User;
use App\Enum\ProductTagCode;
use App\Repository\ProductRepository;
use App\Service\CurrentYearProviderInterface;
use App\Service\DiscountCalculator;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<MealProductOutputDto>
 */
readonly class MealProductsProvider implements ProviderInterface
{
    public function __construct(
        private ProductRepository $productRepository,
        private DiscountCalculator $discountCalculator,
        private CurrentYearProviderInterface $currentYearProvider,
        private Security $security,
    ) {
    }

    /**
     * @return MealProductOutputDto[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $products = $this->productRepository->findByTag(ProductTagCode::JIDLO);
        $user = $this->security->getUser();
        $year = $this->currentYearProvider->getCurrentYear();
        $meals = [];

        foreach ($products as $product) {
            $variants = $product->getVariants();
            if ($variants->isEmpty()) {
                continue;
            }

            $variant = $variants->first();
            if ($variant->getId() === null) {
                continue;
            }

            $dto = MealProductOutputDto::fromProductAndVariant($product, $variant);

            // Sleva orga na jídlo visí na právu, takže ceníková cena nestačí — v matici
            // musí být, co účastník reálně zaplatí, stejně jako u merche a ubytování.
            if ($user instanceof User) {
                $dto->price = $this->discountCalculator->calculateDiscount($product, $user, $year)['finalPrice'];
            }

            $meals[] = $dto;
        }

        return $meals;
    }
}
