<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 *
 * @method Tag|null find($id, $lockMode = null, $lockVersion = null)
 * @method Tag|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method Tag[]    findAll()
 * @method Tag[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    public function save(Tag $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Tag $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByNazev(string $nazev): ?Tag
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.nazev = :nazev')
            ->setParameter('nazev', $nazev)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find tags by category.
     *
     * @return Tag[]
     */
    public function findByKategorie(int $idKategorie): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.idKategorieTagu = :kategorie')
            ->setParameter('kategorie', $idKategorie)
            ->orderBy('t.nazev', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find tags by partial name match.
     *
     * @return Tag[]
     */
    public function findByPartialName(string $searchTerm): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.nazev LIKE :term')
            ->setParameter('term', '%' . $searchTerm . '%')
            ->orderBy('t.nazev', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all tags with their categories, ordered by category and then by tag name.
     *
     * @return Tag[]
     */
    public function findAllWithCategories(): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.kategorieTag', 'k')
            ->addSelect('k')
            ->orderBy('k.poradi', 'ASC')
            ->addOrderBy('t.nazev', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
