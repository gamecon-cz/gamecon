<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\QuickReport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QuickReport>
 *
 * @method QuickReport|null find($id, $lockMode = null, $lockVersion = null)
 * @method QuickReport|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method QuickReport[]    findAll()
 * @method QuickReport[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class QuickReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuickReport::class);
    }
}
