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

    /**
     * Capacity is the variant's. A night is offered unless it is withdrawn or paused, or its room
     * type is withdrawn; room types sit suspended or restricted while their nights sell. Sunday is
     * restricted: offered, its column shown only to holders of the right (AccommodationRules).
     *
     * @param string[] $codes
     *
     * @return array<string, array{vyrobeno: int|null, nabizeno: bool, rezervovano: int|null}> vyrobeno null = unlimited
     */
    public function capacityByVariantCode(array $codes): array
    {
        if ($codes === []) {
            return [];
        }

        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT product_variant.code AS kod_predmetu, product_variant.capacity, product_variant.state,
                    produkt.archived_at, produkt.stav AS stav_typu,
                    COALESCE(product_variant.reserved_for_organizers,
                             vlastni_radek.reserved_for_organizers) AS reserved_for_organizers
             FROM product_variant
             INNER JOIN shop_predmety AS produkt ON produkt.id_predmetu = product_variant.product_id
             LEFT JOIN shop_predmety AS vlastni_radek ON vlastni_radek.kod_predmetu = product_variant.code
             WHERE product_variant.code IN (:codes)',
            [
                'codes' => $codes,
            ],
            [
                'codes' => \Doctrine\DBAL\ArrayParameterType::STRING,
            ],
        );

        $nalezene = [];
        foreach ($rows as $row) {
            $nalezene[(string) $row['kod_predmetu']] = [
                'vyrobeno' => $row['capacity'] === null ? null : (int) $row['capacity'],
                // States only, never nabizet_do: accommodation has always been exempt from it,
                // and honouring it here would lock nights that are still on sale.
                'nabizeno' => $row['archived_at'] === null
                    && (int) $row['stav_typu'] !== ProductStateEnum::RETIRED->value
                    && ! in_array((int) $row['state'], [ProductStateEnum::RETIRED->value, ProductStateEnum::SUSPENDED->value], true),
                // Varianta má přednost před řádkem se stejným kódem (u výchozí varianty je to
                // produkt sám), stejně jako v zapisovači. Typ pokoje se schválně nečte.
                'rezervovano' => $row['reserved_for_organizers'] === null
                    ? null
                    : (int) $row['reserved_for_organizers'],
            ];
        }

        return $nalezene;
    }
}
