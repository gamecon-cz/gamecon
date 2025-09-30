<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ActivityState;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityState>
 *
 * @method ActivityState|null find($id, $lockMode = null, $lockVersion = null)
 * @method ActivityState|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method ActivityState[]    findAll()
 * @method ActivityState[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class ActivityStateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityState::class);
    }

    public function save(ActivityState $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ActivityState $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByName(string $name): ?ActivityState
    {
        return $this->createQueryBuilder('as')
            ->andWhere('as.nazev = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
