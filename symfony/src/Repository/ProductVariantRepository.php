<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Entity\ProductVariant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductVariant>
 */
class ProductVariantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductVariant::class);
    }

    /**
     * Find all variants for a product, ordered by position
     *
     * @return ProductVariant[]
     */
    public function findByProduct(Product $product): array
    {
        return $this->createQueryBuilder('v')
            ->where('v.product = :product')
            ->setParameter('product', $product)
            ->orderBy('v.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find variant by code
     */
    public function findByCode(string $code): ?ProductVariant
    {
        return $this->findOneBy([
            'code' => $code,
        ]);
    }
}
