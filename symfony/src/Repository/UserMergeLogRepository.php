<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\UserMergeLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserMergeLog>
 *
 * @method UserMergeLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserMergeLog|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method UserMergeLog[]    findAll()
 * @method UserMergeLog[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class UserMergeLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserMergeLog::class);
    }
}
