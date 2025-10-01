<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Role;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Role>
 *
 * @method Role|null find($id, $lockMode = null, $lockVersion = null)
 * @method Role|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method Role[]    findAll()
 * @method Role[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class RoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Role::class);
    }

    public function save(Role $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Role $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByKodRole(string $kodRole): ?Role
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.kodRole = :kodRole')
            ->setParameter('kodRole', $kodRole)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByVyznam(string $vyznamRole): ?Role
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.vyznamRole = :vyznamRole')
            ->setParameter('vyznamRole', $vyznamRole)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Role[]
     */
    public function findByRocnik(int $rocnik): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.rocnikRole = :rocnik')
            ->setParameter('rocnik', $rocnik)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Role[]
     */
    public function findByTyp(string $typRole): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.typRole = :typRole')
            ->setParameter('typRole', $typRole)
            ->getQuery()
            ->getResult();
    }
}
