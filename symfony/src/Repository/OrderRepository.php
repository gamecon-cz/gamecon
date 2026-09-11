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
}
