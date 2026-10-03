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
}
