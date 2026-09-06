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
}
