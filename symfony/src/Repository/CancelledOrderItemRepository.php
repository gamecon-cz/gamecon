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

    public function hasCancelledPurchaseOf(Product $product): bool
    {
        return $this->createQueryBuilder('cancelled_item')
            ->select('1')
            ->where('cancelled_item.product = :product')
            ->setParameter('product', $product)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult() !== null;
    }
}
