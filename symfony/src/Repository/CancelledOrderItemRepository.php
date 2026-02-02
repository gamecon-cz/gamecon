<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CancelledOrderItem;
use App\Entity\Product;
use App\Entity\User;
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
     * Get all cancelled items for a customer in a specific year
     *
     * @return CancelledOrderItem[]
     */
    public function findByCustomerAndYear(User $customer, int $year): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.customer = :customer')
            ->andWhere('c.year = :year')
            ->setParameter('customer', $customer)
            ->setParameter('year', $year)
            ->orderBy('c.cancelledAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get cancelled items by cancellation reason for a customer and year
     *
     * Used in dejNazvyZrusenychNakupu from legacy code
     *
     * @return CancelledOrderItem[]
     */
    public function findByCancellationReason(string $reason, User $customer, int $year): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.cancellationReason = :reason')
            ->andWhere('c.customer = :customer')
            ->andWhere('c.year = :year')
            ->setParameter('reason', $reason)
            ->setParameter('customer', $customer)
            ->setParameter('year', $year)
            ->orderBy('c.cancelledAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count cancelled items for a product in a specific year
     */
    public function countByProductAndYear(Product $product, int $year): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.product = :product')
            ->andWhere('c.year = :year')
            ->setParameter('product', $product)
            ->setParameter('year', $year)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Calculate total revenue lost to cancellations in a year
     */
    public function getTotalCancelledRevenueByYear(int $year): string
    {
        $result = $this->createQueryBuilder('c')
            ->select('SUM(c.purchasePrice)')
            ->andWhere('c.year = :year')
            ->setParameter('year', $year)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? '0.00';
    }
}
