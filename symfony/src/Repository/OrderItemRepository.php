<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderItem>
 *
 * @method OrderItem|null find($id, $lockMode = null, $lockVersion = null)
 * @method OrderItem|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method OrderItem[]    findAll()
 * @method OrderItem[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class OrderItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderItem::class);
    }

    public function save(OrderItem $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(OrderItem $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find purchases by customer and year
     *
     * @return OrderItem[]
     */
    public function findByCustomerAndYear(User $customer, int $year): array
    {
        return $this->createQueryBuilder('oi')
            ->where('oi.customer = :customer')
            ->andWhere('oi.year = :year')
            ->setParameter('customer', $customer)
            ->setParameter('year', $year)
            ->orderBy('oi.purchasedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find purchases by product and year
     *
     * @return OrderItem[]
     */
    public function findByProductAndYear(Product $product, int $year): array
    {
        return $this->createQueryBuilder('oi')
            ->where('oi.product = :product')
            ->andWhere('oi.year = :year')
            ->setParameter('product', $product)
            ->setParameter('year', $year)
            ->orderBy('oi.purchasedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count purchases by product and year
     */
    public function countByProductAndYear(Product $product, int $year): int
    {
        return (int) $this->createQueryBuilder('oi')
            ->select('COUNT(oi.id)')
            ->where('oi.product = :product')
            ->andWhere('oi.year = :year')
            ->setParameter('product', $product)
            ->setParameter('year', $year)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get total revenue by product and year
     */
    public function getTotalRevenueByProductAndYear(Product $product, int $year): string
    {
        $result = $this->createQueryBuilder('oi')
            ->select('SUM(oi.purchasePrice)')
            ->where('oi.product = :product')
            ->andWhere('oi.year = :year')
            ->setParameter('product', $product)
            ->setParameter('year', $year)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? '0.00';
    }

    /**
     * Get customer's spending for a year
     */
    public function getCustomerSpendingForYear(User $customer, int $year): string
    {
        $result = $this->createQueryBuilder('oi')
            ->select('SUM(oi.purchasePrice)')
            ->where('oi.customer = :customer')
            ->andWhere('oi.year = :year')
            ->setParameter('customer', $customer)
            ->setParameter('year', $year)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? '0.00';
    }

    /**
     * Check if customer purchased product in year
     */
    public function hasCustomerPurchased(User $customer, Product $product, int $year): bool
    {
        $count = $this->createQueryBuilder('oi')
            ->select('COUNT(oi.id)')
            ->where('oi.customer = :customer')
            ->andWhere('oi.product = :product')
            ->andWhere('oi.year = :year')
            ->setParameter('customer', $customer)
            ->setParameter('product', $product)
            ->setParameter('year', $year)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Count how many times customer purchased product in year
     */
    public function countCustomerPurchases(User $customer, Product $product, int $year): int
    {
        return (int) $this->createQueryBuilder('oi')
            ->select('COUNT(oi.id)')
            ->where('oi.customer = :customer')
            ->andWhere('oi.product = :product')
            ->andWhere('oi.year = :year')
            ->setParameter('customer', $customer)
            ->setParameter('product', $product)
            ->setParameter('year', $year)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get items with discounts in year
     *
     * @return OrderItem[]
     */
    public function findDiscountedItemsForYear(int $year): array
    {
        return $this->createQueryBuilder('oi')
            ->where('oi.year = :year')
            ->andWhere('oi.discountAmount IS NOT NULL')
            ->andWhere('oi.discountAmount > 0')
            ->setParameter('year', $year)
            ->orderBy('oi.discountAmount', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get total discounts given in year
     */
    public function getTotalDiscountsForYear(int $year): string
    {
        $result = $this->createQueryBuilder('oi')
            ->select('SUM(oi.discountAmount)')
            ->where('oi.year = :year')
            ->andWhere('oi.discountAmount IS NOT NULL')
            ->setParameter('year', $year)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? '0.00';
    }

    /**
     * Get items with deleted products (orphaned)
     *
     * @return OrderItem[]
     */
    public function findOrphaned(): array
    {
        return $this->createQueryBuilder('oi')
            ->where('oi.product IS NULL')
            ->andWhere('oi.productName IS NOT NULL')
            ->orderBy('oi.purchasedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get sales statistics for year
     *
     * @return array{totalRevenue: string, totalDiscounts: string, itemsSold: int, uniqueCustomers: int}
     */
    public function getSalesStatsForYear(int $year): array
    {
        $revenue = $this->createQueryBuilder('oi')
            ->select('SUM(oi.purchasePrice)')
            ->where('oi.year = :year')
            ->setParameter('year', $year)
            ->getQuery()
            ->getSingleScalarResult();

        $discounts = $this->getTotalDiscountsForYear($year);

        $itemsSold = (int) $this->createQueryBuilder('oi')
            ->select('COUNT(oi.id)')
            ->where('oi.year = :year')
            ->setParameter('year', $year)
            ->getQuery()
            ->getSingleScalarResult();

        $uniqueCustomers = (int) $this->createQueryBuilder('oi')
            ->select('COUNT(DISTINCT oi.customer)')
            ->where('oi.year = :year')
            ->setParameter('year', $year)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'totalRevenue'    => $revenue ?? '0.00',
            'totalDiscounts'  => $discounts,
            'itemsSold'       => $itemsSold,
            'uniqueCustomers' => $uniqueCustomers,
        ];
    }
}
