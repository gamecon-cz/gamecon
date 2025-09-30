<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ActivityType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityType>
 *
 * @method ActivityType|null find($id, $lockMode = null, $lockVersion = null)
 * @method ActivityType|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method ActivityType[]    findAll()
 * @method ActivityType[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class ActivityTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityType::class);
    }

    public function save(ActivityType $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ActivityType $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByUrl(string $url): ?ActivityType
    {
        return $this->createQueryBuilder('at')
            ->andWhere('at.urlTypuMn = :url')
            ->setParameter('url', $url)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return ActivityType[]
     */
    public function findVisibleInMenu(): array
    {
        return $this->createQueryBuilder('at')
            ->andWhere('at.zobrazitVMenu = :visible')
            ->setParameter('visible', true)
            ->orderBy('at.poradi', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ActivityType[]
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('at')
            ->andWhere('at.aktivni = :active')
            ->setParameter('active', true)
            ->orderBy('at.poradi', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
