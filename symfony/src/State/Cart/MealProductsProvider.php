<?php

declare(strict_types=1);

namespace App\State\Cart;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Cart\MealProductOutputDto;
use App\Repository\ProductRepository;

/**
 * @implements ProviderInterface<MealProductOutputDto>
 */
readonly class MealProductsProvider implements ProviderInterface
{
    public function __construct(
        private ProductRepository $productRepository,
    ) {
    }

    /**
     * @return MealProductOutputDto[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $products = $this->productRepository->findByTag('jidlo');
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

            $meals[] = MealProductOutputDto::fromProductAndVariant($product, $variant);
        }

        return $meals;
    }
}
