<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ReportUsageLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReportUsageLog>
 *
 * @method ReportUsageLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method ReportUsageLog|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method ReportUsageLog[]    findAll()
 * @method ReportUsageLog[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class ReportUsageLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReportUsageLog::class);
    }
}
