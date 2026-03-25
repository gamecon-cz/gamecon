<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\RoleMeaning;
use App\Repository\ProductRepository;
use App\Repository\ProductTagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * ProductService - business logic for products
 *
 * Orchestrates:
 * - Product CRUD operations
 * - Tag management
 * - Archiving/restoring
 * - Integration with DiscountCalculator and CapacityManager
 */
class ProductService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductRepository $productRepository,
        private readonly ProductTagRepository $productTagRepository,
        private readonly DiscountCalculator $discountCalculator,
        private readonly CapacityManager $capacityManager,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Create new product
     *
     * @param string[] $tags
     */
    public function createProduct(
        string $name,
        string $code,
        string $price,
        int $state,
        array $tags = [],
        ?string $description = null,
    ): Product {
        $product = new Product();
        $product->setName($name);
        $product->setCode($code);
        $product->setCurrentPrice($price);
        $product->setState($state);
        $product->setDescription($description ?? '');

        // Add tags
        foreach ($tags as $tag) {
            $product->addTag($this->productTagRepository->findOrCreate($tag));
        }

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return $product;
    }

    /**
     * Update product
     */
    public function updateProduct(Product $product): void
    {
        $this->entityManager->flush();
    }

    /**
     * Archive product (soft-delete)
     */
    public function archiveProduct(Product $product): void
    {
        $product->setArchivedAt($this->clock->now());
        $this->entityManager->flush();
    }

    /**
     * Restore archived product
     */
    public function restoreProduct(Product $product): void
    {
        $product->setArchivedAt(null);
        $this->entityManager->flush();
    }

    /**
     * Delete a product permanently
     */
    public function deleteProduct(Product $product): void
    {
        $this->entityManager->remove($product);
        $this->entityManager->flush();
    }

    /**
     * Add tag to product
     */
    public function addTag(Product $product, string $tag): void
    {
        $product->addTag($this->productTagRepository->findOrCreate($tag));
        $this->entityManager->flush();
    }

    /**
     * Remove tag from product
     */
    public function removeTag(Product $product, string $tag): void
    {
        $tagEntity = $this->productTagRepository->findByName($tag);
        if ($tagEntity !== null) {
            $product->removeTag($tagEntity);
            $this->entityManager->flush();
        }
    }

    /**
     * Replace all tags for product
     *
     * @param string[] $tags
     */
    public function replaceTags(Product $product, array $tags): void
    {
        $this->productTagRepository->replaceProductTags($product, $tags);
    }

    /**
     * Get product with price for user (applying discounts)
     *
     * @return array{
     *     product: Product,
     *     originalPrice: string,
     *     finalPrice: string,
     *     discountAmount: string,
     *     discountReason: string|null
     * }
     */
    public function getProductWithPrice(Product $product, User $user, int $year): array
    {
        $discountInfo = $this->discountCalculator->calculateDiscount($product, $user, $year);

        return [
            'product'        => $product,
            'originalPrice'  => $product->getCurrentPrice(),
            'finalPrice'     => $discountInfo['finalPrice'],
            'discountAmount' => $discountInfo['discountAmount'],
            'discountReason' => $discountInfo['reason'],
        ];
    }

    /**
     * Get multiple products with prices for user
     *
     * @param Product[] $products
     *
     * @return array<int, array{product: Product, originalPrice: string, finalPrice: string, discountAmount: string, discountReason: string|null}>
     */
    public function getProductsWithPrices(array $products, User $user, int $year): array
    {
        $results = [];
        foreach ($products as $product) {
            $results[$product->getId()] = $this->getProductWithPrice($product, $user, $year);
        }

        return $results;
    }

    /**
     * Check if product can be purchased by user
     *
     * @param RoleMeaning[] $roleMeanings
     */
    public function canPurchase(Product $product, ProductVariant $variant, array $roleMeanings = []): bool
    {
        if (! $product->isAvailable()) {
            return false;
        }

        return $this->capacityManager->hasAvailableCapacity($variant, $roleMeanings);
    }

    /**
     * Get product availability info
     *
     * @param RoleMeaning[] $roleMeanings
     *
     * @return array{
     *     available: bool,
     *     reason: string|null,
     *     capacity: array{remaining: int|null, reserved: int, availableForParticipants: int|null, unlimited: bool}|null
     * }
     */
    public function getAvailabilityInfo(Product $product, ProductVariant $variant, array $roleMeanings = []): array
    {
        if (! $product->isAvailable()) {
            return [
                'available' => false,
                'reason'    => 'Produkt není dostupný',
                'capacity'  => null,
            ];
        }

        $capacityInfo = $this->capacityManager->getCapacityInfo($variant);

        if ($capacityInfo['remaining'] !== null && $variant->getAvailableQuantity($roleMeanings) <= 0) {
            return [
                'available' => false,
                'reason'    => 'Vyprodáno',
                'capacity'  => $capacityInfo,
            ];
        }

        return [
            'available' => true,
            'reason'    => null,
            'capacity'  => $capacityInfo,
        ];
    }

    /**
     * Find products by multiple criteria
     *
     * @param array{
     *     tags?: string[],
     *     state?: int,
     *     archived?: bool,
     *     search?: string
     * } $criteria
     *
     * @return Product[]
     */
    public function findProducts(array $criteria): array
    {
        if (isset($criteria['tags']) && $criteria['tags'] !== []) {
            return $this->productRepository->findByAnyTag($criteria['tags']);
        }

        if (isset($criteria['state'])) {
            return $this->productRepository->findByState($criteria['state']);
        }

        if (isset($criteria['archived']) && $criteria['archived']) {
            return $this->productRepository->findArchived();
        }

        return $this->productRepository->findActive();
    }

    /**
     * Get all public products (for shop display)
     *
     * @return Product[]
     */
    public function getPublicProducts(): array
    {
        return $this->productRepository->findPublic();
    }

    /**
     * Bulk archive products
     *
     * @param int[] $productIds
     */
    public function bulkArchive(array $productIds): int
    {
        return $this->productRepository->archiveByIds($productIds);
    }

    /**
     * Bulk restore products
     *
     * @param int[] $productIds
     */
    public function bulkRestore(array $productIds): int
    {
        return $this->productRepository->restoreByIds($productIds);
    }
}
