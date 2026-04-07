<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Team;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Team>
 *
 * @method Team|null find($id, $lockMode = null, $lockVersion = null)
 * @method Team|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method Team[]    findAll()
 * @method Team[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class TeamRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Team::class);
    }

    public function save(Team $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Team $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByKodNaAktivite(int $idAktivity, int $kod): ?Team
    {
        return $this->createQueryBuilder('team')
            ->join('team.aktivity', 'aktivita')
            ->andWhere('aktivita.id = :idAktivity')
            ->andWhere('team.kod = :kod')
            ->setParameter('idAktivity', $idAktivity)
            ->setParameter('kod', $kod)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Team[]
     */
    public function findAllByAktivita(int $idAktivity): array
    {
        return $this->createQueryBuilder('team')
            ->join('team.aktivity', 'aktivita')
            ->andWhere('aktivita.id = :idAktivity')
            ->setParameter('idAktivity', $idAktivity)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Team[]
     */
    public function findVerejneByAktivita(int $idAktivity): array
    {
        return $this->createQueryBuilder('team')
            ->join('team.aktivity', 'aktivita')
            ->andWhere('aktivita.id = :idAktivity')
            ->andWhere('team.verejny = true')
            ->setParameter('idAktivity', $idAktivity)
            ->getQuery()
            ->getResult();
    }

    public function pocetTymuNaAktivite(int $idAktivity): int
    {
        return (int) $this->createQueryBuilder('team')
            ->select('COUNT(team.id)')
            ->join('team.aktivity', 'aktivita')
            ->andWhere('aktivita.id = :idAktivity')
            ->setParameter('idAktivity', $idAktivity)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function isKapitanNaAktivite(int $idUzivatele, int $idAktivity): bool
    {
        return $this->createQueryBuilder('team')
            ->join('team.aktivity', 'aktivita')
            ->andWhere('aktivita.id = :idAktivity')
            ->andWhere('team.kapitan = :idUzivatele')
            ->setParameter('idAktivity', $idAktivity)
            ->setParameter('idUzivatele', $idUzivatele)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult() !== null;
    }

    /**
     * Vrátí týmy starší než $hodin hodin, které nejsou veřejné.
     *
     * @return Team[]
     */
    public function findExpired(int $hodin): array
    {
        return $this->createQueryBuilder('team')
            ->andWhere('team.zalozen < :expirace')
            ->andWhere('team.verejny = false')
            ->setParameter('expirace', new \DateTime("-{$hodin} hours"))
            ->getQuery()
            ->getResult();
    }

    /**
     * Vrátí týmy bez členů (žádný záznam v akce_tym_prihlaseni), založené před více než $minut minutami.
     *
     * @return Team[]
     */
    public function findRozpracovane(int $minut): array
    {
        return $this->createQueryBuilder('team')
            ->leftJoin('team.clenove', 'clen')
            ->andWhere('clen.id IS NULL')
            ->andWhere('team.zalozen < :expirace')
            ->setParameter('expirace', new \DateTime("-{$minut} minutes"))
            ->getQuery()
            ->getResult();
    }
}
