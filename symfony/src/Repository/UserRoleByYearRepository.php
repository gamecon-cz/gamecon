<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\UserRoleByYear;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserRoleByYear>
 *
 * @method UserRoleByYear|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserRoleByYear|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method UserRoleByYear[]    findAll()
 * @method UserRoleByYear[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class UserRoleByYearRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserRoleByYear::class);
    }
}
