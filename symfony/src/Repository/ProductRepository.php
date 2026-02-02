<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 *
 * @method Product|null find($id, $lockMode = null, $lockVersion = null)
 * @method Product|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method Product[]    findAll()
 * @method Product[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function save(Product $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Product $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find all active (non-archived) products
     *
     * @return Product[]
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.archivedAt IS NULL')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find products by tag
     *
     * @return Product[]
     */
    public function findByTag(string $tag): array
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.tags', 't')
            ->where('t.tag = :tag')
            ->andWhere('p.archivedAt IS NULL')
            ->setParameter('tag', $tag)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find products that have ALL specified tags
     *
     * @param string[] $tags
     *
     * @return Product[]
     */
    public function findByTags(array $tags): array
    {
        if ($tags === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('p')
            ->innerJoin('p.tags', 't')
            ->where('t.tag IN (:tags)')
            ->andWhere('p.archivedAt IS NULL')
            ->setParameter('tags', $tags)
            ->groupBy('p.id')
            ->having('COUNT(DISTINCT t.tag) = :tagCount')
            ->setParameter('tagCount', count($tags))
            ->orderBy('p.name', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Find products that have ANY of the specified tags
     *
     * @param string[] $tags
     *
     * @return Product[]
     */
    public function findByAnyTag(array $tags): array
    {
        if ($tags === []) {
            return [];
        }

        return $this->createQueryBuilder('p')
            ->innerJoin('p.tags', 't')
            ->where('t.tag IN (:tags)')
            ->andWhere('p.archivedAt IS NULL')
            ->setParameter('tags', $tags)
            ->groupBy('p.id')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find products by state
     *
     * @return Product[]
     */
    public function findByState(int $state): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.state = :state')
            ->andWhere('p.archivedAt IS NULL')
            ->setParameter('state', $state)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find publicly available products (state=1, not archived, not expired)
     *
     * @return Product[]
     */
    public function findPublic(): array
    {
        $now = new \DateTime();

        return $this->createQueryBuilder('p')
            ->where('p.state = 1')
            ->andWhere('p.archivedAt IS NULL')
            ->andWhere('p.availableUntil IS NULL OR p.availableUntil > :now')
            ->setParameter('now', $now)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find product by code
     */
    public function findByCode(string $code): ?Product
    {
        return $this->createQueryBuilder('p')
            ->where('p.code = :code')
            ->andWhere('p.archivedAt IS NULL')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find archived products
     *
     * @return Product[]
     */
    public function findArchived(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.archivedAt IS NOT NULL')
            ->orderBy('p.archivedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Archive multiple products by IDs
     *
     * @param int[] $ids
     */
    public function archiveByIds(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        $now = new \DateTime();

        return $this->createQueryBuilder('p')
            ->update()
            ->set('p.archivedAt', ':now')
            ->where('p.id IN (:ids)')
            ->andWhere('p.archivedAt IS NULL')
            ->setParameter('now', $now)
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute();
    }

    /**
     * Restore multiple products from archive
     *
     * @param int[] $ids
     */
    public function restoreByIds(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        return $this->createQueryBuilder('p')
            ->update()
            ->set('p.archivedAt', 'NULL')
            ->where('p.id IN (:ids)')
            ->andWhere('p.archivedAt IS NOT NULL')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute();
    }

    /**
     * Find products with expiring availability (within next N days)
     *
     * @return Product[]
     */
    public function findExpiringSoon(int $days = 7): array
    {
        $now = new \DateTime();
        $future = (new \DateTime())->modify(sprintf('+%d days', $days));

        return $this->createQueryBuilder('p')
            ->where('p.availableUntil IS NOT NULL')
            ->andWhere('p.availableUntil BETWEEN :now AND :future')
            ->andWhere('p.archivedAt IS NULL')
            ->setParameter('now', $now)
            ->setParameter('future', $future)
            ->orderBy('p.availableUntil', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find products with low stock (produced quantity)
     *
     * @return Product[]
     */
    public function findLowStock(int $threshold = 10): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.producedQuantity IS NOT NULL')
            ->andWhere('p.producedQuantity <= :threshold')
            ->andWhere('p.producedQuantity > 0')
            ->andWhere('p.archivedAt IS NULL')
            ->setParameter('threshold', $threshold)
            ->orderBy('p.producedQuantity', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
