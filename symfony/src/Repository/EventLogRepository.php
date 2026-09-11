<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EventLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EventLog>
 *
 * @method EventLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method EventLog|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method EventLog[]    findAll()
 * @method EventLog[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class EventLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventLog::class);
    }
}
