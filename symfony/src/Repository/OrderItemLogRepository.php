<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\OrderItemLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderItemLog>
 *
 * @method OrderItemLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method OrderItemLog|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method OrderItemLog[]    findAll()
 * @method OrderItemLog[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class OrderItemLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderItemLog::class);
    }
}
