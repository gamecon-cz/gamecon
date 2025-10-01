<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Accommodation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Accommodation>
 *
 * @method Accommodation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Accommodation|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method Accommodation[]    findAll()
 * @method Accommodation[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class AccommodationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Accommodation::class);
    }

    public function save(Accommodation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Accommodation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Accommodation[]
     */
    public function findByUzivatel(User $uzivatel): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.uzivatel = :uzivatel')
            ->setParameter('uzivatel', $uzivatel)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Accommodation[]
     */
    public function findByRok(int $rok): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.rok = :rok')
            ->setParameter('rok', $rok)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Accommodation[]
     */
    public function findByPokoj(string $pokoj): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.pokoj = :pokoj')
            ->setParameter('pokoj', $pokoj)
            ->getQuery()
            ->getResult();
    }
}
