<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CategoryTag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CategoryTag>
 *
 * @method CategoryTag|null find($id, $lockMode = null, $lockVersion = null)
 * @method CategoryTag|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method CategoryTag[]    findAll()
 * @method CategoryTag[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class CategoryTagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CategoryTag::class);
    }

    public function save(CategoryTag $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CategoryTag $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByNazev(string $nazev): ?CategoryTag
    {
        return $this->createQueryBuilder('k')
            ->andWhere('k.nazev = :nazev')
            ->setParameter('nazev', $nazev)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all categories ordered by 'poradi'
     * @return CategoryTag[]
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
     * @return CategoryTag[]
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
     * @return CategoryTag[]
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
     * @return CategoryTag[]
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
