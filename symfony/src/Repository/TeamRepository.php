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

    /**
     * @param int[] $idAktivit
     */
    public function existujeJinyTymSeStejnymNazvem(int $idTymu, string $nazev, array $idAktivit): bool
    {
        if ($idAktivit === []) {
            return false;
        }

        return (bool) $this->createQueryBuilder('team')
            ->select('COUNT(DISTINCT team.id)')
            ->join('team.aktivity', 'aktivita')
            ->andWhere('LOWER(team.nazev) = LOWER(:nazev)')
            ->andWhere('team.id != :idTymu')
            ->andWhere('aktivita.id IN (:idAktivit)')
            ->setParameter('nazev', $nazev)
            ->setParameter('idTymu', $idTymu)
            ->setParameter('idAktivit', $idAktivit)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findByKapitanNaAktivite(int $idKapitana, int $idAktivity): ?Team
    {
        return $this->createQueryBuilder('team')
            ->join('team.aktivity', 'aktivita')
            ->andWhere('aktivita.id = :idAktivity')
            ->andWhere('team.kapitan = :idKapitana')
            ->setParameter('idAktivity', $idAktivity)
            ->setParameter('idKapitana', $idKapitana)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
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
     * Vrátí nezamčené týmy, jejichž čas expirace je v minulosti.
     *
     * @return Team[]
     */
    public function findExpired(): array
    {
        return $this->createQueryBuilder('team')
            ->andWhere('team.expiruje IS NOT NULL')
            ->andWhere('team.expiruje < :ted')
            ->andWhere('team.zamceny = false')
            ->setParameter('ted', new \DateTime())
            ->getQuery()
            ->getResult();
    }

    /**
     * Vrátí týmy bez přiřazené aktivity.
     *
     * @return Team[]
     */
    public function findBezAktivity(int $rok): array
    {
        return $this->createQueryBuilder('team')
            ->leftJoin('team.aktivity', 'aktivita')
            ->andWhere('aktivita.id IS NULL')
            ->andWhere('team.zalozen >= :startOfYear')
            ->andWhere('team.zalozen < :startOfNextYear')
            ->setParameter('startOfYear', new \DateTime("{$rok}-01-01"))
            ->setParameter('startOfNextYear', new \DateTime(($rok + 1) . '-01-01'))
            ->getQuery()
            ->getResult();
    }

    /**
     * Vrátí připravené týmy (mají alespoň jednoho člena) bez přihlášeného kapitána.
     * Nevalidní stav: kapitán je nastaven na týmu, ale není přihlášen jako člen.
     *
     * @return Team[]
     */
    public function findPripraveneBezKapitana(int $rok): array
    {
        return $this->createQueryBuilder('team')
            ->join('team.aktivity', 'aktivita')
            ->join('team.clenove', 'clen')
            ->leftJoin('team.clenove', 'kapitanRegistrace', 'WITH', 'kapitanRegistrace.uzivatel = team.kapitan')
            ->andWhere('kapitanRegistrace.id IS NULL')
            ->andWhere('aktivita.rok = :rok')
            ->setParameter('rok', $rok)
            ->distinct()
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
