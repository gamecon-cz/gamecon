<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProductBundle;
use App\Entity\ProductVariant;
use App\Enum\RoleMeaning;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductBundle>
 *
 * @method ProductBundle|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductBundle|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method ProductBundle[]    findAll()
 * @method ProductBundle[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class ProductBundleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductBundle::class);
    }

    public function save(ProductBundle $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ProductBundle $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find all forced bundles
     *
     * @return ProductBundle[]
     */
    public function findForced(): array
    {
        return $this->createQueryBuilder('pb')
            ->where('pb.forced = :forced')
            ->setParameter('forced', true)
            ->orderBy('pb.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find bundles applicable to a specific role
     *
     * @return ProductBundle[]
     */
    public function findByRole(string $role): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $ids = $conn->fetchFirstColumn(
            'SELECT product_bundle.id FROM product_bundle WHERE JSON_CONTAINS(product_bundle.applicable_to_roles, :role) = 1 ORDER BY product_bundle.name ASC',
            [
                'role' => json_encode($role),
            ],
        );

        return $ids === [] ? [] : $this->findBy([
            'id' => $ids,
        ]);
    }

    /**
     * Find forced bundles applicable to a specific role
     *
     * @return ProductBundle[]
     */
    public function findForcedByRole(string $role): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $ids = $conn->fetchFirstColumn(
            'SELECT product_bundle.id FROM product_bundle WHERE product_bundle.forced = 1 AND JSON_CONTAINS(product_bundle.applicable_to_roles, :role) = 1 ORDER BY product_bundle.name ASC',
            [
                'role' => json_encode($role),
            ],
        );

        return $ids === [] ? [] : $this->findBy([
            'id' => $ids,
        ]);
    }

    /**
     * Find bundles containing a specific variant
     *
     * @return ProductBundle[]
     */
    public function findByVariant(ProductVariant $variant): array
    {
        return $this->createQueryBuilder('pb')
            ->innerJoin('pb.variants', 'v')
            ->where('v = :variant')
            ->setParameter('variant', $variant)
            ->orderBy('pb.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find forced bundles containing a specific variant
     *
     * @return ProductBundle[]
     */
    public function findForcedByVariant(ProductVariant $variant): array
    {
        return $this->createQueryBuilder('pb')
            ->innerJoin('pb.variants', 'v')
            ->where('v = :variant')
            ->andWhere('pb.forced = :forced')
            ->setParameter('variant', $variant)
            ->setParameter('forced', true)
            ->orderBy('pb.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if variant is part of a forced bundle for given role
     */
    public function isVariantInForcedBundleForRole(ProductVariant $variant, string $role): bool
    {
        $conn = $this->getEntityManager()->getConnection();
        $count = $conn->fetchOne(
            'SELECT COUNT(*) FROM product_bundle
            INNER JOIN product_bundle_variant ON product_bundle.id = product_bundle_variant.bundle_id
            WHERE product_bundle_variant.variant_id = :variantId
              AND product_bundle.forced = 1
              AND JSON_CONTAINS(product_bundle.applicable_to_roles, :role) = 1',
            [
                'variantId' => $variant->getId(),
                'role'      => json_encode($role),
            ],
        );

        return $count > 0;
    }

    /**
     * Get all forced bundles that user with given roles must comply with
     *
     * @param string[] $userRoles
     *
     * @return ProductBundle[]
     */
    public function findMandatoryBundlesForUser(array $userRoles): array
    {
        if ($userRoles === []) {
            return [];
        }

        // Use native SQL because DQL does not support JSON_CONTAINS
        $conn = $this->getEntityManager()->getConnection();

        $roleClauses = [];
        $params = [];
        foreach ($userRoles as $index => $role) {
            $roleClauses[] = sprintf('JSON_CONTAINS(product_bundle.applicable_to_roles, :role%d) = 1', $index);
            $params['role' . $index] = json_encode($role);
        }

        $sql = sprintf(
            'SELECT product_bundle.id FROM product_bundle
            WHERE product_bundle.forced = 1 AND (%s)
            ORDER BY product_bundle.name ASC',
            implode(' OR ', $roleClauses),
        );

        $ids = $conn->fetchFirstColumn($sql, $params);

        if ($ids === []) {
            return [];
        }

        return $this->findBy([
            'id' => $ids,
        ]);
    }

    /**
     * Find the forced bundle a variant belongs to for the given roles.
     * Returns null if the variant can be purchased individually.
     *
     * @param RoleMeaning[] $roleMeanings
     */
    public function findMandatoryBundleForVariant(ProductVariant $variant, array $roleMeanings): ?ProductBundle
    {
        if ($roleMeanings === [] || $variant->getId() === null) {
            return null;
        }

        // Use native SQL because DQL does not support JSON_CONTAINS
        $conn = $this->getEntityManager()->getConnection();

        $roleClauses = [];
        $params = [
            'variantId' => $variant->getId(),
        ];
        foreach ($roleMeanings as $index => $meaning) {
            $roleClauses[] = sprintf('JSON_CONTAINS(product_bundle.applicable_to_roles, :role%d) = 1', $index);
            $params['role' . $index] = json_encode($meaning->value);
        }

        $sql = sprintf(
            'SELECT product_bundle.id FROM product_bundle
            INNER JOIN product_bundle_variant ON product_bundle.id = product_bundle_variant.bundle_id
            WHERE product_bundle_variant.variant_id = :variantId
              AND product_bundle.forced = 1
              AND (%s)
            LIMIT 1',
            implode(' OR ', $roleClauses),
        );

        $bundleId = $conn->fetchOne($sql, $params);

        if ($bundleId === false) {
            return null;
        }

        return $this->find((int) $bundleId);
    }
}
