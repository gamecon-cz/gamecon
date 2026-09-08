<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Enum\ProductStateEnum;
use App\Enum\ProductTagCode;
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
    public function findByTag(ProductTagCode $tag): array
    {
        return $this->createQueryBuilder('product')
            ->innerJoin('product.tags', 'tag')
            ->where('tag.code = :tagCode')
            ->andWhere('product.archivedAt IS NULL')
            ->setParameter('tagCode', $tag->value)
            ->orderBy('product.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find products that have ANY of the specified tags
     *
     * @param ProductTagCode[] $tags
     *
     * @return Product[]
     */
    public function findByAnyTag(array $tags): array
    {
        if ($tags === []) {
            return [];
        }

        return $this->createQueryBuilder('product')
            ->innerJoin('product.tags', 'tag')
            ->where('tag.code IN (:tagCodes)')
            ->andWhere('product.archivedAt IS NULL')
            ->setParameter('tagCodes', array_column($tags, 'value'))
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
    public function findByState(ProductStateEnum $state): array
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
            ->where('product.state = :publicState')
            ->andWhere('product.archivedAt IS NULL')
            ->andWhere('product.availableUntil IS NULL OR product.availableUntil > :now')
            ->setParameter('publicState', ProductStateEnum::PUBLIC)
            ->setParameter('now', $now)
            ->orderBy('product.name', 'ASC')
            ->getQuery()
            ->getResult();
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
}
