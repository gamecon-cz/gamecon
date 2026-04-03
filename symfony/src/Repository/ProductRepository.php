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
        return $this->createQueryBuilder('product')
            ->where('product.archivedAt IS NULL')
            ->orderBy('product.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find products by tag code
     *
     * @return Product[]
     */
    public function findByTag(string $tagCode): array
    {
        return $this->createQueryBuilder('product')
            ->innerJoin('product.tags', 'tag')
            ->where('tag.code = :tagCode')
            ->andWhere('product.archivedAt IS NULL')
            ->setParameter('tagCode', $tagCode)
            ->orderBy('product.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find products that have ALL specified tags
     *
     * @param string[] $tagCodes
     *
     * @return Product[]
     */
    public function findByTags(array $tagCodes): array
    {
        if ($tagCodes === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('product')
            ->innerJoin('product.tags', 'tag')
            ->where('tag.code IN (:tagCodes)')
            ->andWhere('product.archivedAt IS NULL')
            ->setParameter('tagCodes', $tagCodes)
            ->groupBy('product.id')
            ->having('COUNT(DISTINCT tag.code) = :tagCount')
            ->setParameter('tagCount', count($tagCodes))
            ->orderBy('product.name', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Find products that have ANY of the specified tags
     *
     * @param string[] $tagCodes
     *
     * @return Product[]
     */
    public function findByAnyTag(array $tagCodes): array
    {
        if ($tagCodes === []) {
            return [];
        }

        return $this->createQueryBuilder('product')
            ->innerJoin('product.tags', 'tag')
            ->where('tag.code IN (:tagCodes)')
            ->andWhere('product.archivedAt IS NULL')
            ->setParameter('tagCodes', $tagCodes)
            ->groupBy('product.id')
            ->orderBy('product.name', 'ASC')
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
        return $this->createQueryBuilder('product')
            ->where('product.state = :state')
            ->andWhere('product.archivedAt IS NULL')
            ->setParameter('state', $state)
            ->orderBy('product.name', 'ASC')
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

        return $this->createQueryBuilder('product')
            ->where('product.state = 1')
            ->andWhere('product.archivedAt IS NULL')
            ->andWhere('product.availableUntil IS NULL OR product.availableUntil > :now')
            ->setParameter('now', $now)
            ->orderBy('product.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find product by code
     */
    public function findByCode(string $code): ?Product
    {
        return $this->createQueryBuilder('product')
            ->where('product.code = :code')
            ->andWhere('product.archivedAt IS NULL')
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
        return $this->createQueryBuilder('product')
            ->where('product.archivedAt IS NOT NULL')
            ->orderBy('product.archivedAt', 'DESC')
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

        return $this->createQueryBuilder('product')
            ->update()
            ->set('product.archivedAt', ':now')
            ->where('product.id IN (:ids)')
            ->andWhere('product.archivedAt IS NULL')
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

        return $this->createQueryBuilder('product')
            ->update()
            ->set('product.archivedAt', 'NULL')
            ->where('product.id IN (:ids)')
            ->andWhere('product.archivedAt IS NOT NULL')
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

        return $this->createQueryBuilder('product')
            ->where('product.availableUntil IS NOT NULL')
            ->andWhere('product.availableUntil BETWEEN :now AND :future')
            ->andWhere('product.archivedAt IS NULL')
            ->setParameter('now', $now)
            ->setParameter('future', $future)
            ->orderBy('product.availableUntil', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find products that have at least one variant with low remaining stock
     *
     * @return Product[]
     */
    public function findWithLowStockVariants(int $threshold = 10): array
    {
        return $this->createQueryBuilder('product')
            ->innerJoin('product.variants', 'variant')
            ->where('variant.remainingQuantity IS NOT NULL')
            ->andWhere('variant.remainingQuantity <= :threshold')
            ->andWhere('variant.remainingQuantity > 0')
            ->andWhere('product.archivedAt IS NULL')
            ->setParameter('threshold', $threshold)
            ->groupBy('product.id')
            ->getQuery()
            ->getResult();
    }
}
