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
