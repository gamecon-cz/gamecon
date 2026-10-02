<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CancelledOrderItem;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CancelledOrderItem>
 */
class CancelledOrderItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CancelledOrderItem::class);
    }

    /**
     * @param int[] $variantIds
     *
     * @return int[]
     */
    public function cancelledVariantIds(array $variantIds): array
    {
        if ($variantIds === []) {
            return [];
        }

        return array_map('intval', $this->createQueryBuilder('cancelled_item')
            ->select('DISTINCT IDENTITY(cancelled_item.variant)')
            ->where('cancelled_item.variant IN (:variantIds)')
            ->setParameter('variantIds', $variantIds)
            ->getQuery()
            ->getSingleColumnResult());
    }

    public function hasCancelledPurchaseOf(Product $product): bool
    {
        return $this->createQueryBuilder('cancelled_item')
            ->select('1')
            ->innerJoin('cancelled_item.variant', 'zrusena_varianta')
            ->where('zrusena_varianta.product = :product')
            ->setParameter('product', $product)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult() !== null;
    }
}
