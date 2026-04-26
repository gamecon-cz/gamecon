<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TeamMemberRegistration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TeamMemberRegistration>
 *
 * @method TeamMemberRegistration|null find($id, $lockMode = null, $lockVersion = null)
 * @method TeamMemberRegistration|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method TeamMemberRegistration[]    findAll()
 * @method TeamMemberRegistration[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class TeamMemberRegistrationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TeamMemberRegistration::class);
    }

    public function save(TeamMemberRegistration $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TeamMemberRegistration $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByUzivatelAndAktivita(int $idUzivatele, int $idAktivity): ?TeamMemberRegistration
    {
        return $this->createQueryBuilder('reg')
            ->join('reg.team', 'team')
            ->join('team.aktivity', 'aktivita')
            ->andWhere('reg.uzivatel = :idUzivatele')
            ->andWhere('aktivita.id = :idAktivity')
            ->setParameter('idUzivatele', $idUzivatele)
            ->setParameter('idAktivity', $idAktivity)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function pocetClenu(int $idTymu): int
    {
        return (int) $this->createQueryBuilder('reg')
            ->select('COUNT(reg.id)')
            ->andWhere('reg.team = :idTymu')
            ->setParameter('idTymu', $idTymu)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Vrátí nejstarší přihlášení v týmu (nejnižší ID), nebo null pokud je tým prázdný.
     */
    public function findOldestClen(int $idTymu): ?TeamMemberRegistration
    {
        return $this->createQueryBuilder('reg')
            ->andWhere('reg.team = :idTymu')
            ->setParameter('idTymu', $idTymu)
            ->orderBy('reg.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
