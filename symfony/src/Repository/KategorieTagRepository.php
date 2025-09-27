<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\KategorieTag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<KategorieTag>
 *
 * @method KategorieTag|null find($id, $lockMode = null, $lockVersion = null)
 * @method KategorieTag|null findOneBy(array $criteria, array $orderBy = null)
 * @method KategorieTag[]    findAll()
 * @method KategorieTag[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class KategorieTagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, KategorieTag::class);
    }

    public function save(KategorieTag $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(KategorieTag $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByNazev(string $nazev): ?KategorieTag
    {
        return $this->createQueryBuilder('k')
            ->andWhere('k.nazev = :nazev')
            ->setParameter('nazev', $nazev)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all categories ordered by 'poradi'
     * @return KategorieTag[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('k')
            ->orderBy('k.poradi', 'ASC')
            ->addOrderBy('k.nazev', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find main categories (those without parent category)
     * @return KategorieTag[]
     */
    public function findMainCategories(): array
    {
        return $this->createQueryBuilder('k')
            ->andWhere('k.idHlavniKategorie IS NULL')
            ->orderBy('k.poradi', 'ASC')
            ->addOrderBy('k.nazev', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find subcategories of a main category
     * @return KategorieTag[]
     */
    public function findSubcategories(int $idHlavniKategorie): array
    {
        return $this->createQueryBuilder('k')
            ->andWhere('k.idHlavniKategorie = :hlavni')
            ->setParameter('hlavni', $idHlavniKategorie)
            ->orderBy('k.poradi', 'ASC')
            ->addOrderBy('k.nazev', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get hierarchical structure of categories with their tags
     * @return KategorieTag[]
     */
    public function findAllWithTagsHierarchical(): array
    {
        return $this->createQueryBuilder('k')
            ->leftJoin('k.tagy', 't')
            ->addSelect('t')
            ->orderBy('k.poradi', 'ASC')
            ->addOrderBy('k.nazev', 'ASC')
            ->addOrderBy('t.nazev', 'ASC')
            ->getQuery()
            ->getResult();
    }
}