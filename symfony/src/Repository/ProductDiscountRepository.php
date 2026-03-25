<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Entity\ProductDiscount;
use App\Enum\RoleMeaning;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductDiscount>
 *
 * @method ProductDiscount|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductDiscount|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method ProductDiscount[]    findAll()
 * @method ProductDiscount[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class ProductDiscountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductDiscount::class);
    }

    public function save(ProductDiscount $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ProductDiscount $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find discount for product and role
     */
    public function findByProductAndRole(Product $product, string $role): ?ProductDiscount
    {
        return $this->createQueryBuilder('pd')
            ->where('pd.product = :product')
            ->andWhere('pd.role = :role')
            ->setParameter('product', $product)
            ->setParameter('role', $role)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all discounts for a product
     *
     * @return ProductDiscount[]
     */
    public function findByProduct(Product $product): array
    {
        return $this->createQueryBuilder('pd')
            ->where('pd.product = :product')
            ->setParameter('product', $product)
            ->orderBy('pd.discountPercent', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all discounts for a role
     *
     * @return ProductDiscount[]
     */
    public function findByRole(string $role): array
    {
        return $this->createQueryBuilder('pd')
            ->where('pd.role = :role')
            ->setParameter('role', $role)
            ->orderBy('pd.discountPercent', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find free products for a role (100% discount)
     *
     * @return ProductDiscount[]
     */
    public function findFreeProductsForRole(string $role): array
    {
        return $this->createQueryBuilder('pd')
            ->where('pd.role = :role')
            ->andWhere('pd.discountPercent = :hundred')
            ->setParameter('role', $role)
            ->setParameter('hundred', '100.00')
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if product has discount for role
     */
    public function hasDiscountForRole(Product $product, string $role): bool
    {
        $count = $this->createQueryBuilder('pd')
            ->select('COUNT(pd.id)')
            ->where('pd.product = :product')
            ->andWhere('pd.role = :role')
            ->setParameter('product', $product)
            ->setParameter('role', $role)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Get best discount for product among user's role meanings
     *
     * @param RoleMeaning[] $roleMeanings
     */
    public function findBestDiscountForProduct(Product $product, array $roleMeanings): ?ProductDiscount
    {
        if ($roleMeanings === []) {
            return null;
        }

        return $this->createQueryBuilder('pd')
            ->where('pd.product = :product')
            ->andWhere('pd.role IN (:roles)')
            ->setParameter('product', $product)
            ->setParameter('roles', $roleMeanings)
            ->orderBy('pd.discountPercent', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get all products with discounts for a role
     *
     * @return Product[]
     */
    public function findProductsWithDiscountForRole(RoleMeaning $role): array
    {
        $result = $this->createQueryBuilder('pd')
            ->select('IDENTITY(pd.product)')
            ->where('pd.role = :role')
            ->setParameter('role', $role)
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 1);
    }

    /**
     * Delete all discounts for a product
     */
    public function deleteByProduct(Product $product): int
    {
        return $this->createQueryBuilder('pd')
            ->delete()
            ->where('pd.product = :product')
            ->setParameter('product', $product)
            ->getQuery()
            ->execute();
    }
}
