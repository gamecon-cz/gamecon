<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 *
 * @method Order|null find($id, $lockMode = null, $lockVersion = null)
 * @method Order|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method Order[]    findAll()
 * @method Order[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function save(Order $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Order $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find pending order for customer in year (cart)
     */
    public function findPendingForCustomer(User $customer, int $year): ?Order
    {
        return $this->createQueryBuilder('o')
            ->where('o.customer = :customer')
            ->andWhere('o.year = :year')
            ->andWhere('o.status = :status')
            ->setParameter('customer', $customer)
            ->setParameter('year', $year)
            ->setParameter('status', Order::STATUS_PENDING)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find completed orders for customer in year
     *
     * @return Order[]
     */
    public function findCompletedForCustomer(User $customer, int $year): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.customer = :customer')
            ->andWhere('o.year = :year')
            ->andWhere('o.status = :status')
            ->setParameter('customer', $customer)
            ->setParameter('year', $year)
            ->setParameter('status', Order::STATUS_COMPLETED)
            ->orderBy('o.completedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all orders by status
     *
     * @return Order[]
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.status = :status')
            ->setParameter('status', $status)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get total revenue for year
     */
    public function getTotalRevenueForYear(int $year): string
    {
        $result = $this->createQueryBuilder('o')
            ->select('SUM(o.totalPrice)')
            ->where('o.year = :year')
            ->andWhere('o.status = :status')
            ->setParameter('year', $year)
            ->setParameter('status', Order::STATUS_COMPLETED)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? '0.00';
    }
}
