<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\UserRoleLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserRoleLog>
 *
 * @method UserRoleLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserRoleLog|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method UserRoleLog[]    findAll()
 * @method UserRoleLog[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class UserRoleLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserRoleLog::class);
    }
}
