<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Enum\RoleMeaning;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * @return User[]
     */
    public function findByRoleMeaning(RoleMeaning $vyznam): array
    {
        return $this->createQueryBuilder('user')
            ->innerJoin('user.userRoles', 'userRole')
            ->innerJoin('userRole.role', 'role')
            ->where('role.vyznamRole = :vyznam')
            ->setParameter('vyznam', $vyznam)
            ->getQuery()
            ->getResult();
    }
}
