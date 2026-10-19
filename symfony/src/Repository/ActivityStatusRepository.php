<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ActivityStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityStatus>
 *
 * @method ActivityStatus|null find($id, $lockMode = null, $lockVersion = null)
 * @method ActivityStatus|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method ActivityStatus[]    findAll()
 * @method ActivityStatus[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class ActivityStatusRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityStatus::class);
    }
}
