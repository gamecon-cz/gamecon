<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ActivityRegistrationState;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityRegistrationState>
 *
 * @method ActivityRegistrationState|null find($id, $lockMode = null, $lockVersion = null)
 * @method ActivityRegistrationState|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method ActivityRegistrationState[]    findAll()
 * @method ActivityRegistrationState[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class ActivityRegistrationStateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityRegistrationState::class);
    }

    public function save(ActivityRegistrationState $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ActivityRegistrationState $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByName(string $name): ?ActivityRegistrationState
    {
        return $this->createQueryBuilder('ars')
            ->andWhere('ars.nazev = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
